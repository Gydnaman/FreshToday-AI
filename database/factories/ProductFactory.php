<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $organicPrefixes = ['Organic', 'Fresh', 'Local', 'Free-range', 'Wild-caught', 'Hand-picked', 'Sun-ripened'];
        $items = ['Tomato', 'Carrot', 'Spinach', 'Apple', 'Pear', 'Salmon', 'Chicken', 'Egg', 'Mushroom', 'Kale', 'Beetroot', 'Pumpkin', 'Lettuce', 'Strawberry', 'Blueberry'];

        $name = fake()->randomElement($organicPrefixes) . ' ' . fake()->randomElement($items);

        return [
            'name'              => $name,
            'description'       => fake()->sentence(10),
            'price'             => fake()->randomFloat(2, 10, 300),
            'image'             => 'https://placehold.co/600x400/4ade80/ffffff?text=' . urlencode($name),
            'carbon_footprint'  => fake()->randomFloat(3, 0.1, 5.0),
            'stock'             => fake()->numberBetween(0, 200),
            'category_id'       => Category::factory(),
            'is_organic'        => fake()->boolean(80),
            'origin'            => 'HK ' . fake()->randomElement(['New Territories', 'Lantau Island', 'Sai Kung', 'Yuen Long']),
        ];
    }

    /** 有机产品 */
    public function organic(): static
    {
        return $this->state(fn () => ['is_organic' => true]);
    }

    /** 缺货 */
    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    /** 低价（用于促销场景测试） */
    public function cheap(): static
    {
        return $this->state(fn () => ['price' => fake()->randomFloat(2, 5, 30)]);
    }
}
