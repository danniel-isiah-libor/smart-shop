<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Create an order with one item per [stock, quantity] pair, returning the order and its products.
 *
 * @param  array<int, array{0: int, 1: int}>  $lines
 * @return array{0: Order, 1: array<int, Product>}
 */
function stockOrder(array $lines, string $state = 'pending'): array
{
    $order = Order::factory()->{$state}()->create();
    $products = [];

    foreach ($lines as [$stock, $quantity]) {
        $product = Product::factory()->create(['price' => 10, 'stock' => $stock]);
        OrderItem::factory()->for($order)->for($product)->create([
            'unit_price' => 10,
            'quantity' => $quantity,
            'line_total' => 10 * $quantity,
        ]);
        $products[] = $product;
    }

    return [$order, $products];
}

test('marking a pending order paid decrements each product by its item quantity', function () {
    [$order, [$a, $b]] = stockOrder([[10, 2], [5, 5]]);

    $order->moveTo(OrderStatus::Paid);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($a->fresh()->stock)->toBe(8)
        ->and($b->fresh()->stock)->toBe(0);
});

test('cancelling a paid order restores the stock it took', function () {
    [$order, [$a, $b]] = stockOrder([[10, 2], [5, 3]]);

    $order->moveTo(OrderStatus::Paid);
    $order->refresh()->moveTo(OrderStatus::Cancelled);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($a->fresh()->stock)->toBe(10)
        ->and($b->fresh()->stock)->toBe(5);
});

test('cancelling a pending order changes no stock', function () {
    [$order, [$product]] = stockOrder([[10, 4]]);

    $order->moveTo(OrderStatus::Cancelled);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(10);
});

test('shipping and delivering an order changes no stock', function () {
    [$order, [$product]] = stockOrder([[10, 4]]);

    $order->moveTo(OrderStatus::Paid);
    $order->refresh()->moveTo(OrderStatus::Shipped);
    $order->refresh()->moveTo(OrderStatus::Delivered);

    expect($product->fresh()->stock)->toBe(6);
});

test('insufficient stock blocks the payment and rolls everything back', function () {
    Notification::fake();
    [$order, [$enough, $short]] = stockOrder([[10, 2], [1, 3]]);

    expect(fn () => $order->moveTo(OrderStatus::Paid))->toThrow(DomainException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->statusHistories()->count())->toBe(0)
        ->and($enough->fresh()->stock)->toBe(10)
        ->and($short->fresh()->stock)->toBe(1);

    Notification::assertNothingSent();
});

test('stock exactly equal to the quantity can be reserved', function () {
    [$order, [$product]] = stockOrder([[3, 3]]);

    $order->moveTo(OrderStatus::Paid);

    expect($product->fresh()->stock)->toBe(0);
});

test('a cancelled order cannot be cancelled again so stock is restored once', function () {
    [$order, [$product]] = stockOrder([[10, 2]]);
    $order->moveTo(OrderStatus::Paid);
    $order->refresh()->moveTo(OrderStatus::Cancelled);

    expect(fn () => $order->refresh()->moveTo(OrderStatus::Cancelled))->toThrow(DomainException::class);

    expect($product->fresh()->stock)->toBe(10);
});

test('a cancelled order cannot be paid again', function () {
    [$order, [$product]] = stockOrder([[10, 2]]);
    $order->moveTo(OrderStatus::Cancelled);

    expect(fn () => $order->refresh()->moveTo(OrderStatus::Paid))->toThrow(DomainException::class);

    expect($product->fresh()->stock)->toBe(10);
});

test('two stale copies of the same order cannot both take stock', function () {
    [$order, [$product]] = stockOrder([[10, 2]]);
    $first = Order::find($order->id);
    $second = Order::find($order->id);

    $first->moveTo(OrderStatus::Paid);

    expect(fn () => $second->moveTo(OrderStatus::Paid))->toThrow(DomainException::class);

    expect($product->fresh()->stock)->toBe(8)
        ->and($order->statusHistories()->count())->toBe(1);
});

test('reserveStock throws when stock is short and takes nothing', function () {
    [$order, [$enough, $short]] = stockOrder([[10, 2], [1, 3]]);

    expect(fn () => DB::transaction(fn () => $order->reserveStock()))->toThrow(DomainException::class);

    expect($enough->fresh()->stock)->toBe(10)
        ->and($short->fresh()->stock)->toBe(1);
});

test('restoreStock adds each item quantity back', function () {
    [$order, [$a, $b]] = stockOrder([[10, 2], [0, 7]]);

    $order->restoreStock();

    expect($a->fresh()->stock)->toBe(12)
        ->and($b->fresh()->stock)->toBe(7);
});

test('restoreStock on an order without items does nothing', function () {
    $order = Order::factory()->create();

    $order->restoreStock();

    expect($order->items()->count())->toBe(0);
});
