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
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.$this->faker->unique()->numerify('######'),
            'status' => OrderStatus::Pending,
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'shipping_fee' => 0,
            'total' => 0,
            'shipping_name' => $this->faker->name(),
            'shipping_phone' => $this->faker->phoneNumber(),
            'shipping_address_line1' => $this->faker->streetAddress(),
            'shipping_address_line2' => null,
            'shipping_city' => $this->faker->city(),
            'shipping_state' => $this->faker->state(),
            'shipping_postal_code' => $this->faker->postcode(),
            'shipping_country' => $this->faker->country(),
            'notes' => null,
        ];
    }

    /**
     * Indicate that the order is pending.
     */
    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending]);
    }

    /**
     * Indicate that the order has been paid.
     */
    public function paid(): static
    {
        return $this->state(['status' => OrderStatus::Paid]);
    }

    /**
     * Indicate that the order has been shipped.
     */
    public function shipped(): static
    {
        return $this->state(['status' => OrderStatus::Shipped]);
    }

    /**
     * Indicate that the order has been delivered.
     */
    public function delivered(): static
    {
        return $this->state(['status' => OrderStatus::Delivered]);
    }

    /**
     * Indicate that the order has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled]);
    }
}
