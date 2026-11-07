<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
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
            'courier' => fake()->randomElement(['LBC Express', 'J&T Express', 'JRS Express', 'Ninja Van', 'Flash Express']),
            'tracking_number' => strtoupper(fake()->unique()->bothify('??########??')),
            'shipped_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the shipment has not shipped yet.
     */
    public function unshipped(): static
    {
        return $this->state(fn (): array => ['shipped_at' => null]);
    }
}
