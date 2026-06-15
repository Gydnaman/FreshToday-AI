<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPreference>
 */
class UserPreferenceFactory extends Factory
{
    protected $model = UserPreference::class;

    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'usage_purpose'   => fake()->randomElement(['Eat Healthier', 'Support Local Farms', 'Reduce Carbon Footprint']),
            'dietary_habits'  => fake()->randomElement(['No Restrictions', 'Vegetarian/Vegan', 'Keto/Low Carb', 'High Protein']),
            'goals'           => fake()->randomElement(['Lose weight', 'Gain muscle', 'More energy', 'Eat greener']),
            'allergies'       => [],
            'household_size'  => fake()->numberBetween(1, 6),
            'cooking_skill'   => fake()->randomElement(['Beginner', 'Intermediate', 'Advanced']),
            'budget_hkd'      => fake()->randomFloat(2, 200, 1500),
        ];
    }
}
