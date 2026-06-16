<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'stripe',
            'provider_txn_id' => 'pi_'.fake()->unique()->bothify('??##??##??##??##??##'),
            'amount' => fake()->randomFloat(2, 50, 500),
            'currency' => 'HKD',
            'status' => 'pending',
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn () => ['status' => 'succeeded', 'paid_at' => now()]);
    }
}
