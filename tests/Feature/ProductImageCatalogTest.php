<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImageCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrected_catalog_images_exist_in_public_storage(): void
    {
        $files = [
            'chicken-breast.jpg', 'chili-sauce.jpg', 'choysum.jpg', 'coconut.jpg',
            'daikon.jpg', 'eggs.jpg', 'ginger.jpg', 'honey.jpg', 'lime.jpg',
            'lychee.jpg', 'mullet.jpg', 'mushroom.jpg', 'napa-cabbage.jpg',
            'peanut-butter.jpg', 'pomelo.jpg', 'pumpkin.jpg',
            'purple-sweet-potato.jpg', 'scallop.jpg', 'soysauce.jpg',
            'starfruit.jpg', 'tofu.jpg', 'xo-sauce.jpg',
        ];

        foreach ($files as $file) {
            $this->assertFileExists(public_path('images/products/catalog-v2/'.$file));
        }
    }

    public function test_correction_migration_updates_existing_product_image_paths(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => '本地手工豉油',
            'image' => 'images/products/seed/soysauce.jpg',
        ]);

        $migration = require database_path('migrations/2026_08_10_130000_correct_product_image_catalog.php');
        $migration->up();

        $this->assertSame(
            'images/products/catalog-v2/soysauce.jpg',
            $product->fresh()->image,
        );
    }
}
