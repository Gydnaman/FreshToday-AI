<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name'        => fake()->words(2, true) . ' Plan',
            'description' => fake()->sentence(),
            'price'       => fake()->randomFloat(2, 100, 1000),
            'duration'    => fake()->randomElement([7, 14, 30]),
            'cycle'       => fake()->randomElement(['weekly', 'biweekly', 'monthly']),
            'is_active'   => true,
        ];
    }
}
