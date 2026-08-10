<?php

namespace Tests\Feature\Database;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeedImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_product_images_are_versioned_public_files(): void
    {
        $this->seed();

        $products = Product::query()->get(['name', 'image']);

        $this->assertCount(24, $products);

        foreach ($products as $product) {
            $this->assertStringStartsWith('images/products/seed/', $product->image, $product->name);
            $this->assertFileExists(public_path($product->image), $product->name);
        }
    }
}
