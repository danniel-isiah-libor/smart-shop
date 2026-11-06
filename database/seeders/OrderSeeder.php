<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates 3 customers with 2-4 orders each. Every order has 1-5 distinct
     * products, and its total equals the sum of its line totals.
     */
    public function run(): void
    {
        $products = Product::query()->get();

        User::factory()->count(3)->create()->each(function (User $customer) use ($products): void {
            foreach (range(1, fake()->numberBetween(2, 4)) as $ignored) {
                $this->createOrder($customer, $products->random(fake()->numberBetween(1, 5)));
            }
        });
    }

    /**
     * Create an order for the customer with one line per given product.
     *
     * @param  Collection<int, Product>  $products
     */
    private function createOrder(User $customer, $products): void
    {
        $order = Order::factory()->for($customer)->create([
            'status' => fake()->randomElement(OrderStatus::cases()),
        ]);

        $totalCents = 0;

        foreach ($products as $product) {
            $quantity = fake()->numberBetween(1, 3);
            $lineCents = (int) round($product->price * 100) * $quantity;
            $totalCents += $lineCents;

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => $quantity,
                'line_total' => $lineCents / 100,
            ]);
        }

        $order->update([
            'subtotal' => $totalCents / 100,
            'total' => $totalCents / 100,
        ]);
    }
}
