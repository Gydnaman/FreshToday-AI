<?php

namespace Tests\Feature\Web;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Checkout 业务规则测试
 *
 * 关注"流程正确性"，区别于 CartAuthGuardTest 的"边界鉴权"：
 *   - 空 items / 空购物车 → 拒收
 *   - 商品已售罄 → 拒收（GUARD-I1，409）
 *   - 下单成功后购物车应清空
 *   - 支付时 provider 非法 → 422
 *   - Bob 想支付 Alice 的订单 → 403
 */
class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $stranger;

    private Product $product;

    private Product $outOfStock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->stranger = User::factory()->create();
        $this->product = Product::factory()->create([
            'price' => 50, 'stock' => 20,
            'category_id' => Category::factory(),
        ]);
        $this->outOfStock = Product::factory()->create([
            'price' => 30, 'stock' => 0,
            'category_id' => Category::factory(),
        ]);
    }

    /** items 为空 → 422 validation error */
    public function test_checkout_with_empty_cart_fails(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/orders', [
            'items' => [],
            'shipping_address' => $this->shippingAddress(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // 没有任何订单被创建
        $this->assertEquals(0, Order::count());
    }

    /** 商品库存为 0 → GUARD-I1，409 */
    public function test_checkout_with_out_of_stock_fails(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $this->outOfStock->id, 'quantity' => 1],
            ],
            'shipping_address' => $this->shippingAddress(),
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'GUARD-I1');

        $this->assertEquals(0, Order::count());
        $this->assertEquals(0, $this->outOfStock->fresh()->stock); // 没扣成
    }

    /** 结算成功后购物车应被清空 */
    public function test_checkout_clears_cart_after_order(): void
    {
        Sanctum::actingAs($this->user);

        // 加 2 个不同商品
        $spinach = Product::factory()->create([
            'price' => 8, 'stock' => 10,
            'category_id' => Category::factory(),
        ]);
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $spinach->id,
            'quantity' => 1,
        ]);

        $this->assertEquals(2, CartItem::where('user_id', $this->user->id)->count());

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
                ['product_id' => $spinach->id,       'quantity' => 1],
            ],
            'shipping_address' => $this->shippingAddress(),
        ]);

        $response->assertCreated();
        $this->assertEquals(0, CartItem::where('user_id', $this->user->id)->count(),
            '结算后购物车应清空');
    }

    /** pay 时 provider 不是 stripe/payme → 422 validation */
    public function test_pay_with_invalid_provider_fails(): void
    {
        Sanctum::actingAs($this->user);
        $order = $this->placeOrderFor($this->user, $this->product, 1);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'provider' => 'bogus_gateway',
            'return_url' => 'https://shop.example.com/done',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    /**
     * 跨用户支付：Bob 想 pay Alice 的订单 → 403 NOT_OWNER
     * 这是 GUARD-G0 在 API 层的体现
     */
    public function test_pay_with_non_owner_returns_403(): void
    {
        // Alice 下单
        $aliceOrder = $this->placeOrderFor($this->user, $this->product, 1);

        // Bob 试图支付
        Sanctum::actingAs($this->stranger);

        $response = $this->postJson("/api/orders/{$aliceOrder->id}/pay", [
            'provider' => 'stripe',
            'return_url' => 'https://shop.example.com/done',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'NOT_OWNER');

        // 订单状态没动
        $this->assertEquals('pending', $aliceOrder->fresh()->status->value);
    }

    // ── helpers ─────────────────────────────────────────────

    /** 提单 helper：直接调 service，跳过 cart 路径以便测试聚焦 */
    private function placeOrderFor(User $user, Product $product, int $qty): Order
    {
        return app(OrderService::class)->createOrder(
            user: $user,
            items: [['product_id' => $product->id, 'quantity' => $qty]],
            shippingAddress: $this->shippingAddress(),
        );
    }

    private function shippingAddress(): array
    {
        return [
            'name' => 'Tester',
            'phone' => '+85298765432',
            'address' => '88 Test Street',
            'district' => 'NT',
            'currency' => 'HKD',
        ];
    }
}
