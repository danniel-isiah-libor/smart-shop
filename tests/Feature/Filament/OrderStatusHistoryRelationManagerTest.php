<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\RelationManagers\OrderStatusHistoriesRelationManager;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
});

function historyManager(Order $order)
{
    return Livewire::test(OrderStatusHistoriesRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => EditOrder::class,
    ]);
}

test('it lists the order history with actor names and a system fallback', function () {
    $order = Order::factory()->create();
    $staff = User::factory()->create(['name' => 'Staff Sam']);
    $byStaff = OrderStatusHistory::factory()->for($order)->create(['user_id' => $staff->id]);
    $bySystem = OrderStatusHistory::factory()->for($order)->bySystem()->create([
        'from_status' => OrderStatus::Paid,
        'to_status' => OrderStatus::Shipped,
    ]);

    historyManager($order)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$byStaff, $bySystem])
        ->assertSee('Staff Sam')
        ->assertSee('System');
});

test('it only shows the history of its own order', function () {
    $order = Order::factory()->create();
    $mine = OrderStatusHistory::factory()->for($order)->create();
    $other = OrderStatusHistory::factory()->create();

    historyManager($order)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
});

test('it is read only', function () {
    $order = Order::factory()->create();

    historyManager($order)
        ->assertTableActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});

test('the order page shows the history manager', function () {
    $order = Order::factory()->create();

    Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
        ->assertSeeLivewire(OrderStatusHistoriesRelationManager::class);
});
