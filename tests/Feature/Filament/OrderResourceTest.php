<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\OrderStatusHistoriesRelationManager;
use App\Filament\Widgets\OrderStatusStats;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
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
            'status' => OrderStatus::Pending,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 12.5],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::query()->where('user_id', $customer->id)->firstOrFail();
    $item = $order->items()->firstOrFail();

    expect($order->order_number)->toStartWith('ORD-')
        ->and($order->status)->toBe(OrderStatus::Pending)
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

test('the create form only offers the pending status', function () {
    Livewire::test(CreateOrder::class)
        ->assertFormFieldExists('status', fn (Select $field): bool => array_keys($field->getOptions()) === [OrderStatus::Pending->value]);
});

test('the create form cannot start an order as anything but pending', function (OrderStatus $status) {
    $customer = User::factory()->create();
    $product = Product::factory()->create(['price' => 10, 'stock' => 5]);

    Livewire::test(CreateOrder::class)
        ->fillForm([
            'user_id' => $customer->id,
            'status' => $status,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 10],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['status']);

    expect(Order::query()->where('user_id', $customer->id)->exists())->toBeFalse()
        ->and($product->fresh()->stock)->toBe(5);
})->with([OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Cancelled]);

test('the view page is reachable from the resource routes and the table', function () {
    $order = Order::factory()->create();

    $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertSuccessful();

    Livewire::test(ListOrders::class)->assertTableActionExists('view');
});

test('the view page shows customer, items, coupon, totals, status and history', function () {
    $customer = User::factory()->create(['name' => 'Ana Reyes', 'email' => 'ana@example.test']);
    $coupon = Coupon::factory()->create(['code' => 'VIEW250', 'discount' => 250]);
    $order = Order::factory()->for($customer)->pending()->create([
        'shipping_city' => 'Cebu City',
        'billing_city' => 'Davao City',
        'tax' => 150,
        'shipping_fee' => 50,
    ]);
    $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
    $order->setProductQuantity($product, 2);
    $order->refresh()->applyCoupon($coupon);
    $order->refresh()->moveTo(OrderStatus::Paid);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Ana Reyes')
        ->assertSee('ana@example.test')
        ->assertSee('Cebu City')
        ->assertSee('Davao City')
        ->assertSee($product->name)
        ->assertSee('₱1,000.00')
        ->assertSee('₱2,000.00')
        ->assertSee('VIEW250')
        ->assertSee('₱250.00')
        ->assertSee('₱150.00')
        ->assertSee('₱50.00')
        ->assertSee('₱1,950.00')
        ->assertSee('Paid')
        ->assertSeeLivewire(OrderStatusHistoriesRelationManager::class);
});

test('the view page hides the coupon section when the order has no coupon', function () {
    $order = Order::factory()->create();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertDontSee('VIEW250');
});

test('the view page keeps the coupon code after the coupon is deleted', function () {
    $coupon = Coupon::factory()->create(['code' => 'REMOVED5']);
    $order = Order::factory()->withCoupon($coupon)->create();
    $coupon->delete();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee('REMOVED5');
});

test('the view page offers status actions', function () {
    $order = Order::factory()->pending()->create();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionVisible('markAsPaid')
        ->assertActionVisible('cancel')
        ->assertActionHidden('ship');
});

test('the date range filter includes both boundary days', function () {
    $beforeRange = Order::factory()->create(['created_at' => '2026-02-28 23:59:59']);
    $firstDay = Order::factory()->create(['created_at' => '2026-03-01 00:00:00']);
    $lastDay = Order::factory()->create(['created_at' => '2026-03-10 23:59:59']);
    $afterRange = Order::factory()->create(['created_at' => '2026-03-11 00:00:00']);

    Livewire::test(ListOrders::class)
        ->filterTable('created_at', ['created_from' => '2026-03-01', 'created_until' => '2026-03-10'])
        ->assertCanSeeTableRecords([$firstDay, $lastDay])
        ->assertCanNotSeeTableRecords([$beforeRange, $afterRange]);
});

test('the date range filter works with only one bound', function () {
    $early = Order::factory()->create(['created_at' => '2026-01-05 12:00:00']);
    $late = Order::factory()->create(['created_at' => '2026-06-05 12:00:00']);

    Livewire::test(ListOrders::class)
        ->filterTable('created_at', ['created_from' => '2026-03-01', 'created_until' => null])
        ->assertCanSeeTableRecords([$late])
        ->assertCanNotSeeTableRecords([$early])
        ->filterTable('created_at', ['created_from' => null, 'created_until' => '2026-03-01'])
        ->assertCanSeeTableRecords([$early])
        ->assertCanNotSeeTableRecords([$late]);
});

test('the date range filter with from after until shows nothing and does not error', function () {
    $order = Order::factory()->create(['created_at' => '2026-03-05 12:00:00']);

    Livewire::test(ListOrders::class)
        ->filterTable('created_at', ['created_from' => '2026-03-10', 'created_until' => '2026-03-01'])
        ->assertSuccessful()
        ->assertCanNotSeeTableRecords([$order]);
});

test('the date range and status filters combine', function () {
    $paidInRange = Order::factory()->paid()->create(['created_at' => '2026-03-05 12:00:00']);
    $pendingInRange = Order::factory()->pending()->create(['created_at' => '2026-03-05 12:00:00']);
    $paidOutOfRange = Order::factory()->paid()->create(['created_at' => '2026-05-05 12:00:00']);

    Livewire::test(ListOrders::class)
        ->filterTable('status', OrderStatus::Paid->value)
        ->filterTable('created_at', ['created_from' => '2026-03-01', 'created_until' => '2026-03-31'])
        ->assertCanSeeTableRecords([$paidInRange])
        ->assertCanNotSeeTableRecords([$pendingInRange, $paidOutOfRange]);
});

test('coupon actions show only on pending orders', function (string $page) {
    $pending = Order::factory()->pending()->withCoupon()->create();

    Livewire::test($page, ['record' => $pending->getRouteKey()])
        ->assertActionVisible('applyCoupon')
        ->assertActionVisible('removeCoupon');
})->with([ViewOrder::class, EditOrder::class]);

test('coupon actions are hidden on non-pending orders', function (string $state, string $page) {
    $order = Order::factory()->{$state}()->withCoupon()->create();

    Livewire::test($page, ['record' => $order->getRouteKey()])
        ->assertActionHidden('applyCoupon')
        ->assertActionHidden('removeCoupon');
})->with(['paid', 'shipped', 'delivered', 'cancelled'])->with([ViewOrder::class, EditOrder::class]);

test('the apply coupon action discounts the order', function () {
    $order = Order::factory()->pending()->create();
    $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
    $order->setProductQuantity($product, 2);
    $coupon = Coupon::factory()->create(['code' => 'ADMIN50', 'discount' => 50]);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('applyCoupon', ['coupon_id' => $coupon->id])
        ->assertNotified();

    expect($order->fresh()->coupon_code)->toBe('ADMIN50')
        ->and($order->fresh()->discount)->toBe('50.00')
        ->and($order->fresh()->total)->toBe('150.00');
});

test('the apply coupon action only offers usable coupons', function () {
    $order = Order::factory()->pending()->create();
    Coupon::factory()->inactive()->create();
    Coupon::factory()->expired()->create();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('applyCoupon', ['coupon_id' => Coupon::query()->where('is_active', false)->value('id')])
        ->assertHasActionErrors();

    expect($order->fresh()->coupon_id)->toBeNull();
});

test('applying a coupon to an empty order shows a danger notification and changes nothing', function () {
    $order = Order::factory()->pending()->create();
    $coupon = Coupon::factory()->create();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('applyCoupon', ['coupon_id' => $coupon->id])
        ->assertNotified();

    expect($order->fresh()->coupon_id)->toBeNull();
});

test('the remove coupon action restores the total', function () {
    $order = Order::factory()->pending()->create();
    $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
    $order->setProductQuantity($product, 2);
    $order->refresh()->applyCoupon(Coupon::factory()->create(['discount' => 50]));

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('removeCoupon');

    expect($order->fresh()->coupon_id)->toBeNull()
        ->and($order->fresh()->discount)->toBe('0.00')
        ->and($order->fresh()->total)->toBe('200.00');
});

test('marking an order paid from the admin takes stock and a stock shortfall is reported', function () {
    $order = Order::factory()->pending()->create();
    $product = Product::factory()->create(['price' => 10, 'stock' => 1]);
    OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3, 'unit_price' => 10, 'line_total' => 30]);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('markAsPaid');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($product->fresh()->stock)->toBe(1);

    $product->update(['stock' => 5]);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('markAsPaid');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($product->fresh()->stock)->toBe(2);
});
