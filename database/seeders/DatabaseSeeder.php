<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $suppliers = Supplier::factory(10)->create();

        foreach ($this->catalog() as $categoryName => $products) {
            $category = Category::query()->create(['name' => $categoryName]);

            foreach ($products as $name => $price) {
                $category->products()->create([
                    'name' => $name,
                    'description' => fake()->sentence(),
                    'price' => $price,
                    'stock' => fake()->numberBetween(20, 200),
                    'supplier_id' => $suppliers->random()->id,
                ]);
            }
        }

        Coupon::factory(20)->create();

        $this->call(OrderSeeder::class);

        $orders = Order::query()->get();

        Shipment::factory(15)
            ->recycle($orders)
            ->create();
    }

    /**
     * Get the seed catalog of categories, product names and prices.
     *
     * @return array<string, array<string, float>>
     */
    private function catalog(): array
    {
        return [
            'Electronics' => [
                'Wireless Earbuds' => 59.99,
                'Bluetooth Speaker' => 39.90,
                '27-inch Monitor' => 229.00,
                'Mechanical Keyboard' => 89.50,
                'Wireless Mouse' => 24.99,
                'USB-C Hub' => 34.95,
                'Portable Power Bank' => 29.99,
                'Webcam 1080p' => 49.00,
                'Smartwatch' => 149.00,
                'Noise-Cancelling Headphones' => 199.99,
            ],
            'Clothing' => [
                'Cotton T-Shirt' => 14.99,
                'Slim Fit Jeans' => 49.95,
                'Hooded Sweatshirt' => 39.00,
                'Running Shorts' => 19.99,
                'Rain Jacket' => 79.90,
                'Wool Socks (3-pack)' => 12.50,
                'Leather Belt' => 27.00,
                'Baseball Cap' => 16.99,
                'Summer Dress' => 44.50,
                'Fleece Gloves' => 18.00,
            ],
            'Home & Kitchen' => [
                'Non-Stick Frying Pan' => 32.99,
                'Chef Knife' => 54.00,
                'Ceramic Dinner Set' => 69.90,
                'French Press Coffee Maker' => 28.50,
                'Cotton Bath Towels (4-pack)' => 35.00,
                'Memory Foam Pillow' => 29.99,
                'Glass Food Containers' => 24.95,
                'Electric Kettle' => 36.00,
                'Scented Candle' => 11.99,
                'Bamboo Cutting Board' => 17.50,
            ],
            'Books' => [
                'Mystery Paperback' => 9.99,
                'Cooking Cookbook' => 22.50,
                'Science Fiction Novel' => 12.99,
                'Personal Finance Guide' => 18.00,
                'Illustrated Atlas' => 34.95,
                "Children's Storybook" => 7.50,
                'Biography Hardcover' => 21.00,
                'Programming Handbook' => 44.99,
                'Travel Journal' => 13.50,
                'History of Art' => 39.00,
            ],
            'Sports & Outdoors' => [
                'Yoga Mat' => 25.00,
                'Adjustable Dumbbell Set' => 119.00,
                'Camping Tent (2-person)' => 89.99,
                'Insulated Water Bottle' => 21.95,
                'Resistance Bands Set' => 15.99,
                'Trekking Backpack 40L' => 74.50,
                'Jump Rope' => 8.99,
                'Football' => 19.50,
                'Cycling Helmet' => 45.00,
                'Foam Roller' => 22.99,
            ],
        ];
    }
}
