<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['is_admin' => false]);
        $this->bob = User::factory()->create(['is_admin' => false]);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /** 未登录访问 /admin/products 应重定向 */
    public function test_guest_redirected()
    {
        $this->get('/admin/products')
            ->assertRedirectContains('/login');
    }

    /** 普通用户创建产品自动绑定 user_id */
    public function test_store_assigns_user_id()
    {
        $this->actingAs($this->alice);
        $cat = Category::factory()->create();

        $this->post('/admin/products', [
            'name' => 'Alice 有机菜',
            'category_id' => $cat->id,
            'price' => 30,
            'stock' => 10,
        ])->assertRedirect();

        $this->assertDatabaseHas('products', [
            'name' => 'Alice 有机菜',
            'user_id' => $this->alice->id,
        ]);
    }

    /** 普通用户只能看到自己的产品 */
    public function test_index_filters_by_owner()
    {
        Product::factory()->create(['name' => 'Alice product', 'user_id' => $this->alice->id, 'status' => 'draft']);
        Product::factory()->create(['name' => 'Bob product',   'user_id' => $this->bob->id,   'status' => 'draft']);

        $this->actingAs($this->alice)
            ->get('/admin/products')
            ->assertSee('Alice product')
            ->assertDontSee('Bob product');
    }

    /** 管理员可以看到全部产品（包括无主的） */
    public function test_admin_sees_all_products()
    {
        Product::factory()->create(['name' => 'Alice product',  'user_id' => $this->alice->id, 'status' => 'draft']);
        Product::factory()->create(['name' => 'Bob product',    'user_id' => $this->bob->id,   'status' => 'draft']);
        Product::factory()->create(['name' => 'Orphan product', 'user_id' => null,            'status' => 'draft']);

        $this->actingAs($this->admin)
            ->get('/admin/products')
            ->assertSee('Alice product')
            ->assertSee('Bob product')
            ->assertSee('Orphan product');
    }

    /** 用户A不能编辑用户B的产品 */
    public function test_cannot_edit_others_product()
    {
        $product = Product::factory()->create(['user_id' => $this->bob->id, 'status' => 'draft']);

        $this->actingAs($this->alice)
            ->get("/admin/products/{$product->id}/edit")
            ->assertForbidden();
    }

    /** 用户A不能更新用户B的产品 */
    public function test_cannot_update_others_product()
    {
        $product = Product::factory()->create(['user_id' => $this->bob->id, 'status' => 'draft']);

        $this->actingAs($this->alice)
            ->put("/admin/products/{$product->id}", [
                'name' => 'hacked', 'category_id' => $product->category_id,
                'price' => 10, 'stock' => 10,
            ])
            ->assertForbidden();
    }

    /** 管理员可以编辑任何人的产品 */
    public function test_admin_can_edit_any_product()
    {
        $product = Product::factory()->create(['user_id' => $this->alice->id, 'status' => 'draft']);

        $this->actingAs($this->admin)
            ->get("/admin/products/{$product->id}/edit")
            ->assertOk();
    }
}
