<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'order_no'     => 'GB' . now()->format('Ymd') . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT),
            'status'       => OrderStatus::Pending->value,
            'total_price'  => fake()->randomFloat(2, 50, 500),
            'placed_at'    => now(),
            'shipping_address' => [
                'name'       => fake()->name(),
                'phone'      => '+852' . fake()->numerify('########'),
                'address'    => fake()->streetAddress(),
                'district'   => fake()->randomElement(['HK', 'KL', 'NT', 'Lantau']),
                'currency'   => 'HKD',
            ],
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Paid->value, 'paid_at' => now()]);
    }

    public function shipped(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Shipped->value, 'paid_at' => now(), 'tracking_no' => 'SF' . random_int(100000, 999999)]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Cancelled->value, 'cancelled_at' => now(), 'cancel_reason' => 'payment_timeout_30min']);
    }
}
