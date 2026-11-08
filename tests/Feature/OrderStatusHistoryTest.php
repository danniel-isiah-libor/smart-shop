<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

test('moveTo records who moved the order, from what, to what', function () {
    $staff = User::factory()->create();
    $order = Order::factory()->create();

    $order->moveTo(OrderStatus::Paid, $staff);

    $history = $order->statusHistories()->sole();

    expect($history->from_status)->toBe(OrderStatus::Pending)
        ->and($history->to_status)->toBe(OrderStatus::Paid)
        ->and($history->user_id)->toBe($staff->id)
        ->and($history->created_at)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('moveTo defaults the actor to the authenticated user', function () {
    $staff = User::factory()->create();
    $order = Order::factory()->create();

    $this->actingAs($staff);
    $order->moveTo(OrderStatus::Cancelled);

    expect($order->statusHistories()->sole()->user_id)->toBe($staff->id);
});

test('a change made without a user is recorded as the system', function () {
    $order = Order::factory()->paid()->create();

    $order->moveTo(OrderStatus::Shipped);

    expect($order->statusHistories()->sole()->user_id)->toBeNull();
});

test('an invalid transition throws and writes no history', function () {
    $order = Order::factory()->create();

    expect(fn () => $order->moveTo(OrderStatus::Delivered))->toThrow(DomainException::class);

    expect($order->statusHistories()->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('the status and its history roll back together', function () {
    $order = Order::factory()->create();

    OrderStatusHistory::creating(fn () => throw new RuntimeException('boom'));

    expect(fn () => $order->moveTo(OrderStatus::Paid))->toThrow(RuntimeException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(OrderStatusHistory::query()->count())->toBe(0);

    OrderStatusHistory::flushEventListeners();
});

test('history is listed latest first', function () {
    $order = Order::factory()->create();

    $order->moveTo(OrderStatus::Paid);
    $this->travel(1)->minutes();
    $order->moveTo(OrderStatus::Shipped);

    expect($order->statusHistories->pluck('to_status')->all())->toBe([OrderStatus::Shipped, OrderStatus::Paid]);
});

test('checkout records a pending to paid change by the buyer, once, even on retry', function () {
    Event::fake();
    $buyer = User::factory()->create();
    $product = Product::factory()->create(['stock' => 5]);
    OrderItem::factory()->create([
        'order_id' => Order::cartFor($buyer)->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
    $address = ['name' => 'A', 'address_line1' => 'B', 'address_line2' => null, 'city' => 'C', 'state' => 'D', 'postal_code' => '1', 'country' => 'PH'];
    $key = (string) Str::uuid();

    $order = Order::place($buyer, $key, $address, $address);
    Order::place($buyer, $key, $address, $address);

    $history = $order->statusHistories()->sole();

    expect($history->from_status)->toBe(OrderStatus::Pending)
        ->and($history->to_status)->toBe(OrderStatus::Paid)
        ->and($history->user_id)->toBe($buyer->id);
});

test('deleting an order deletes its history', function () {
    $order = Order::factory()->create();
    $order->moveTo(OrderStatus::Paid);

    $order->delete();

    expect(OrderStatusHistory::query()->count())->toBe(0);
});

test('deleting the actor keeps the history as a system change', function () {
    $staff = User::factory()->create();
    $order = Order::factory()->create();
    $order->moveTo(OrderStatus::Paid, $staff);

    $staff->delete();

    expect($order->statusHistories()->sole()->user_id)->toBeNull();
});
