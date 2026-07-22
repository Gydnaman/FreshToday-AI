<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_product_detail_keeps_existing_response_contract(): void
    {
        $product = Product::factory()->create([
            'name' => 'API Product',
            'status' => Product::STATUS_PUBLISHED,
        ]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'API Product')
            ->assertJsonStructure(['data' => ['id', 'name', 'price', 'stock', 'image_url', 'category']]);
    }

    public function test_draft_product_api_detail_returns_404(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_DRAFT]);

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_archived_product_api_detail_returns_404(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ARCHIVED]);

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_missing_product_api_detail_returns_404(): void
    {
        $this->getJson('/api/products/999999')->assertNotFound();
    }
}
