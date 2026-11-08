<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'from_status' => OrderStatus::Pending,
            'to_status' => OrderStatus::Paid,
        ];
    }

    /**
     * The first recorded status, with no previous one.
     */
    public function initial(): static
    {
        return $this->state(['from_status' => null]);
    }

    /**
     * A change made by the system rather than a user.
     */
    public function bySystem(): static
    {
        return $this->state(['user_id' => null]);
    }
}
