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

    public function test_product_name_is_escaped_inside_the_page_title(): void
    {
        $maliciousName = '</title><script>alert("stored-title")</script>';
        $product = Product::factory()->create([
            'name' => $maliciousName,
            'status' => Product::STATUS_PUBLISHED,
        ]);

        $content = $this->get(route('products.show', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<title>'.e($maliciousName).' - ', $content);
        $this->assertStringNotContainsString('<title>'.$maliciousName, $content);
        $this->assertStringNotContainsString('<script>alert("stored-title")</script>', $content);

        $template = file_get_contents(resource_path('views/shop/product-detail.blade.php'));
        $this->assertIsString($template);
        $this->assertStringContainsString("@section('title')", $template);
        $this->assertStringContainsString('{{ $product->name }}', $template);
        $this->assertStringNotContainsString("@section('title', \$product->name)", $template);
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

    public function test_in_stock_detail_renders_quantity_and_add_to_cart_contract(): void
    {
        $product = Product::factory()->create([
            'name' => 'Fresh Carrot',
            'price' => 18,
            'stock' => 7,
            'status' => Product::STATUS_PUBLISHED,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('id="product-quantity"', false)
            ->assertSee('min="1"', false)
            ->assertSee('max="7"', false)
            ->assertSee('addToCartAuth(', false)
            ->assertSee('Fresh Carrot');
    }

    public function test_sold_out_detail_disables_purchase_controls(): void
    {
        $product = Product::factory()->outOfStock()->create([
            'status' => Product::STATUS_PUBLISHED,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('data-testid="sold-out-button"', false)
            ->assertSee('disabled', false)
            ->assertDontSee('id="product-quantity"', false);
    }

    public function test_optional_product_fields_are_not_rendered_when_null(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_PUBLISHED,
            'origin' => null,
            'carbon_footprint' => null,
            'image' => null,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('data-testid="product-image-placeholder"', false)
            ->assertDontSee('data-testid="product-origin"', false)
            ->assertDontSee('data-testid="product-carbon"', false);
    }

    public function test_catalog_links_product_image_and_title_to_detail(): void
    {
        $product = Product::factory()->create([
            'name' => 'Linked Product',
            'status' => Product::STATUS_PUBLISHED,
        ]);

        $response = $this->get(route('catalog'))->assertOk();
        $detailUrl = route('products.show', $product);

        $response->assertSee($detailUrl, false);
        $this->assertSame(2, substr_count($response->getContent(), 'href="'.$detailUrl.'"'));
        $response->assertSee('addToCartAuth('.$product->id, false);
    }

    public function test_guest_cart_fallback_preserves_selected_quantity_contract(): void
    {
        $product = Product::factory()->create([
            'stock' => 7,
            'status' => Product::STATUS_PUBLISHED,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('fallbackLocalAdd(productId, name, price, qty)', false)
            ->assertSee('for (let i = 0; i < qty; i++)', false);
    }
}
