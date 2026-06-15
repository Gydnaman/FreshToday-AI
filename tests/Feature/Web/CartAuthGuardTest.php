<?php

namespace Tests\Feature\Web;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 鉴权与用户隔离测试
 *
 * 验证：
 *   - 未登录访问 cart / orders → 401（不是 500）
 *   - 无效 token → 401（不是 500）
 *   - 用户 A 的购物车对用户 B 不可见
 *
 * 配套的端到端流程在 EndToEndCheckoutTest，本文件专注鉴权边界。
 */
class CartAuthGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;
    private User $bob;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create(['email' => 'alice@example.com']);
        $this->bob = User::factory()->create(['email' => 'bob@example.com']);
        $this->product = Product::factory()->create([
            'price' => 10,
            'stock' => 100,
            'category_id' => Category::factory(),
        ]);
    }

    /** 未带 token POST /api/cart → 401 JSON */
    public function test_unauthenticated_post_cart_returns_401(): void
    {
        $response = $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /** 未带 token GET /api/cart → 401 JSON */
    public function test_unauthenticated_get_cart_returns_401(): void
    {
        $response = $this->getJson('/api/cart');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /** POST /api/orders 没有 token → 401（不是 500） */
    public function test_token_required_for_order_create(): void
    {
        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
            'shipping_address' => [
                'name' => 'X', 'address' => 'X', 'currency' => 'HKD',
            ],
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /** Bearer 一个完全乱写的 token → 401（关键：不能崩 500） */
    public function test_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer this-is-not-a-real-token-xyz')
            ->getJson('/api/cart');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        // 同样的姿势 POST 也要 401，不是 500
        $response2 = $this->withHeader('Authorization', 'Bearer invalid.token.value')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity'   => 1,
            ]);

        $response2->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * 用户 A 加购后，用户 B 调 GET /api/cart 不应看到 A 的商品
     * （隔离边界 —— 数据不串）
     */
    public function test_cart_isolated_between_users(): void
    {
        // Alice 加 3 个 apple
        Sanctum::actingAs($this->alice);
        $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity'   => 3,
        ])->assertCreated();

        $aliceCart = $this->getJson('/api/cart')->assertOk();
        $this->assertCount(1, $aliceCart->json('items'));
        $this->assertEquals(3, $aliceCart->json('item_count'));

        // Bob 登录 —— 应看到空购物车
        Sanctum::actingAs($this->bob);
        $bobCart = $this->getJson('/api/cart')->assertOk();
        $this->assertCount(0, $bobCart->json('items'));
        $this->assertEquals(0, $bobCart->json('item_count'));
        $this->assertEquals(0, $bobCart->json('total'));

        // Bob 加 1 个 spinach（另一个 product，避免 firstOrNew 冲突逻辑）
        $spinach = Product::factory()->create([
            'price' => 5, 'stock' => 10,
            'category_id' => Category::factory(),
        ]);
        $this->postJson('/api/cart', [
            'product_id' => $spinach->id,
            'quantity'   => 1,
        ])->assertCreated();

        $bobCart2 = $this->getJson('/api/cart')->assertOk();
        $this->assertCount(1, $bobCart2->json('items'));
        $this->assertEquals(1, $bobCart2->json('item_count'));

        // DB 层断言：Alice 的 cart_items 仍只属于 Alice
        $this->assertEquals(
            1,
            CartItem::where('user_id', $this->alice->id)->count()
        );
        $this->assertEquals(
            1,
            CartItem::where('user_id', $this->bob->id)->count()
        );
    }
}
