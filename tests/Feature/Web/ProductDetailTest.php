<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_product_detail_is_publicly_visible(): void
    {
        $category = Category::factory()->create(['name' => 'Leafy Greens']);
        $product = Product::factory()->create([
            'name' => 'Local Organic Choy Sum',
            'description' => 'Picked this morning in Yuen Long.',
            'price' => 28,
            'stock' => 7,
            'status' => Product::STATUS_PUBLISHED,
            'category_id' => $category->id,
        ]);

        $this->assertSame(Product::STATUS_PUBLISHED, $product->status);

        $this->get("/products/{$product->id}")
            ->assertOk()
            ->assertViewIs('shop.product-detail')
            ->assertViewHas('product', fn (Product $viewProduct) => $viewProduct->is($product))
            ->assertSee('Local Organic Choy Sum')
            ->assertSee('Picked this morning in Yuen Long.')
            ->assertSee('Leafy Greens');
    }

    public function test_draft_product_detail_returns_404(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_DRAFT]);

        $this->get("/products/{$product->id}")->assertNotFound();
    }

    public function test_archived_product_detail_returns_404(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ARCHIVED]);

        $this->get("/products/{$product->id}")->assertNotFound();
    }

    public function test_missing_product_detail_returns_404(): void
    {
        $this->get('/products/999999')->assertNotFound();
    }
}
