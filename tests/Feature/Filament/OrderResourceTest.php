<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Widgets\OrderStatusStats;
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

test('editing an order recalculates totals', function () {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create(['quantity' => 1, 'unit_price' => 5, 'line_total' => 5]);

    Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm([
            'items' => [
                "record-{$item->id}" => ['product_id' => $item->product_id, 'quantity' => 4, 'unit_price' => 5],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($order->fresh()->total)->toBe('20.00');
});

test('the list page shows the order count per status', function () {
    Order::factory()->paid()->count(2)->create();
    Order::factory()->cancelled()->create();

    Livewire::test(ListOrders::class)->assertSuccessful();

    Livewire::test(OrderStatusStats::class)
        ->assertSeeInOrder(['Pending', '0', 'Paid', '2', 'Shipped', '0', 'Delivered', '0', 'Cancelled', '1']);
});

test('status actions only show for allowed moves and move the order', function () {
    $order = Order::factory()->pending()->create();

    Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionVisible('markAsPaid')
        ->assertActionVisible('cancel')
        ->assertActionHidden('ship')
        ->assertActionHidden('markAsDelivered')
        ->callAction('markAsPaid');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('the status field is read-only when editing an order', function () {
    $order = Order::factory()->pending()->create();

    Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
        ->assertFormFieldDisabled('status')
        ->fillForm(['status' => OrderStatus::Delivered])
        ->call('save');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});
