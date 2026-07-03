<?php

namespace Tests\Feature\Web;

use App\Enums\OrderStatus;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 端到端 Web 结算流程集成测试
 *
 * 覆盖 PRD 关键用户旅程：
 *   浏览商品 → 加购 → 查购物车 → 改数量 → 删商品 → 结算 → 支付跳转
 *
 * 测的是 API 层（jQuery 前端不测），用 $this->actingAs 模拟已登录用户（session 认证）。
 */
class EndToEndCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    private Product $apple;

    private Product $spinach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();
        $this->apple = Product::factory()->create([
            'name' => 'Organic Apple',
            'price' => 25.00,
            'stock' => 50,
            'category_id' => $this->category->id,
        ]);
        $this->spinach = Product::factory()->create([
            'name' => 'Fresh Spinach',
            'price' => 18.50,
            'stock' => 30,
            'category_id' => $this->category->id,
        ]);
    }

    /** 匿名访客也能浏览商品列表（公开端点） */
    public function test_anonymous_user_can_browse_products(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'price', 'stock'],
                ],
                'meta' => ['pagination'],
            ]);

        // 我们造了 2 个商品
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    /** 已登录用户：POST /api/cart 把商品加入购物车 */
    public function test_authenticated_user_can_add_to_cart(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/cart', [
            'product_id' => $this->apple->id,
            'quantity' => 3,
        ]);

        $response->assertCreated()
            ->assertJsonPath('item.product_id', $this->apple->id)
            ->assertJsonPath('item.quantity', 3);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->apple->id,
            'quantity' => 3,
        ]);
    }

    /** GET /api/cart 返回当前用户购物车 + total + item_count */
    public function test_authenticated_user_can_view_cart(): void
    {
        $this->actingAs($this->user);
        $this->addItem($this->apple, 2);   // 25 * 2 = 50
        $this->addItem($this->spinach, 1); // 18.5

        $response = $this->getJson('/api/cart');

        $response->assertOk()
            ->assertJsonPath('item_count', 3)
            ->assertJsonPath('total', 68.50);

        $this->assertCount(2, $response->json('items'));
    }

    /** PATCH /api/cart/{item} 修改数量 */
    public function test_authenticated_user_can_update_quantity(): void
    {
        $this->actingAs($this->user);
        $item = $this->addItem($this->apple, 2);

        $response = $this->patchJson("/api/cart/{$item->id}", [
            'quantity' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('item.quantity', 5);

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 5,
        ]);
    }

    /** DELETE /api/cart/{item} 移除商品 */
    public function test_authenticated_user_can_remove_item(): void
    {
        $this->actingAs($this->user);
        $item = $this->addItem($this->apple, 2);

        $response = $this->deleteJson("/api/cart/{$item->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    /** 完整 checkout：POST /api/orders 创建订单，订单里带 cart 商品 */
    public function test_checkout_creates_order_with_cart_items(): void
    {
        $this->actingAs($this->user);
        $this->addItem($this->apple, 2);    // 25 * 2 = 50
        $this->addItem($this->spinach, 1);  // 18.5

        $address = $this->shippingAddress();

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $this->apple->id,   'quantity' => 2],
                ['product_id' => $this->spinach->id, 'quantity' => 1],
            ],
            'shipping_address' => $address,
        ]);

        $response->assertCreated()
            ->assertJsonPath('order.status', OrderStatus::Pending->value);

        $orderId = $response->json('order.id');
        $this->assertNotNull($orderId);

        // 库存应被预占
        $this->assertEquals(48, $this->apple->fresh()->stock);   // 50 - 2
        $this->assertEquals(29, $this->spinach->fresh()->stock); // 30 - 1

        // 订单 + order_product 行
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('order_product', [
            'order_id' => $orderId,
            'product_id' => $this->apple->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('order_product', [
            'order_id' => $orderId,
            'product_id' => $this->spinach->id,
            'quantity' => 1,
        ]);
    }

    /** POST /api/orders/{order}/pay 返回 redirect_url 给前端跳网关 */
    public function test_checkout_pay_returns_redirect_url(): void
    {
        $this->actingAs($this->user);
        $this->addItem($this->apple, 1);

        $orderResponse = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $this->apple->id, 'quantity' => 1],
            ],
            'shipping_address' => $this->shippingAddress(),
        ])->assertCreated();

        $orderId = $orderResponse->json('order.id');

        $response = $this->postJson("/api/orders/{$orderId}/pay", [
            'provider' => 'stripe',
            'return_url' => 'https://shop.example.com/checkout/success',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['payment', 'redirect_url']);

        $this->assertStringContainsString(
            'https://shop.example.com/checkout/success',
            $response->json('redirect_url')
        );
    }

    // ── helpers ─────────────────────────────────────────────

    /** 加购并返回 CartItem 持久化对象 */
    private function addItem(Product $product, int $qty): CartItem
    {
        return CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => $qty,
        ]);
    }

    private function shippingAddress(): array
    {
        return [
            'name' => 'Test User',
            'phone' => '+85291234567',
            'address' => '1 Test Road',
            'district' => 'KL',
            'currency' => 'HKD',
        ];
    }
}
