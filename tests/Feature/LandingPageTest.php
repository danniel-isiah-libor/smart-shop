<?php

use App\Models\Product;

test('landing page shows login and register links to guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false);
});

test('landing page shows only the five latest available products', function () {
    $outOfStock = Product::factory()->create(['name' => 'Soldout Item', 'stock' => 0]);

    $available = collect(range(1, 6))->map(fn (int $i) => Product::factory()->create([
        'name' => "Available Item {$i}",
        'stock' => 10,
        'created_at' => now()->addMinutes($i),
    ]));

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee($outOfStock->name)
        ->assertDontSee('Available Item 1')
        ->assertSee('Available Item 2')
        ->assertSee('Available Item 6');
});

test('landing page does not offer purchasing', function () {
    Product::factory()->create(['stock' => 5]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSeeText('Add to cart')
        ->assertDontSeeText('Buy now');
});
