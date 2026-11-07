<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the list page shows orders and filters by status', function () {
    $paid = Order::factory()->paid()->create();
    $cancelled = Order::factory()->cancelled()->create();

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$paid, $cancelled])
        ->filterTable('status', OrderStatus::Paid->value)
        ->assertCanSeeTableRecords([$paid])
        ->assertCanNotSeeTableRecords([$cancelled]);
});

test('creating an order generates its number, item details and totals', function () {
    $customer = User::factory()->create();
    $product = Product::factory()->create(['price' => 10]);

    Livewire::test(CreateOrder::class)
        ->fillForm([
            'user_id' => $customer->id,
            'status' => OrderStatus::Paid,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 12.5],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::query()->where('user_id', $customer->id)->firstOrFail();
    $item = $order->items()->firstOrFail();

    expect($order->order_number)->toStartWith('ORD-')
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($item->product_name)->toBe($product->name)
        ->and($item->line_total)->toBe('37.50')
        ->and($order->total)->toBe('37.50');
});

test('an order requires at least one item', function () {
    Livewire::test(CreateOrder::class)
        ->fillForm([
            'user_id' => User::factory()->create()->id,
            'items' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['items']);
});

test('editing an order updates its status and recalculates totals', function () {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create(['quantity' => 1, 'unit_price' => 5, 'line_total' => 5]);

    Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm([
            'status' => OrderStatus::Shipped,
            'items' => [
                "record-{$item->id}" => ['product_id' => $item->product_id, 'quantity' => 4, 'unit_price' => 5],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped)
        ->and($order->fresh()->total)->toBe('20.00');
});
