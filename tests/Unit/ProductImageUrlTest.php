<?php

namespace Tests\Unit;

use App\Models\Product;
use Tests\TestCase;

class ProductImageUrlTest extends TestCase
{
    public function test_image_url_returns_null_when_image_is_null(): void
    {
        $product = new Product(['image' => null]);

        $this->assertNull($product->image_url);
    }

    public function test_image_url_returns_external_url_as_is(): void
    {
        $product = new Product(['image' => 'https://placehold.co/400x400/4ade80/ffffff?text=Foo']);

        $this->assertSame('https://placehold.co/400x400/4ade80/ffffff?text=Foo', $product->image_url);
    }

    public function test_image_url_prefixes_local_storage_path(): void
    {
        $product = new Product(['image' => 'products/2026/07/04/test.jpg']);

        $this->assertSame('http://localhost/storage/products/2026/07/04/test.jpg', $product->image_url);
    }

    public function test_image_url_included_in_json_serialization(): void
    {
        $product = new Product([
            'name' => 'Test Product',
            'image' => 'products/2026/07/04/test.jpg',
        ]);

        $serialized = $product->toArray();

        $this->assertArrayHasKey('image_url', $serialized);
        $this->assertSame('http://localhost/storage/products/2026/07/04/test.jpg', $serialized['image_url']);
    }
}
