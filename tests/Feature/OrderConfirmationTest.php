<?php

use App\Enums\OrderStatus;
use App\Livewire\OrderConfirmation;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);

    $this->get(route('orders.confirmation', $order))->assertRedirect(route('login'));
});

test('owner sees their order confirmation', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'status' => OrderStatus::Paid,
        'total' => 224,
        'shipping_name' => 'Maria Clara',
    ]);

    $this->actingAs($user);

    Livewire::test(OrderConfirmation::class, ['order' => $order])
        ->assertSee($order->order_number)
        ->assertSee('Maria Clara')
        ->assertSeeHtml('₱224.00')
        ->assertSee('Paid');
});

test("another customer's order returns 404", function () {
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);

    $this->actingAs(User::factory()->create())
        ->get(route('orders.confirmation', $order))
        ->assertNotFound();
});

test('an unplaced cart has no confirmation page', function () {
    $user = User::factory()->create();
    $cart = $user->cart();

    $this->actingAs($user)->get(route('orders.confirmation', $cart))->assertNotFound();
});
