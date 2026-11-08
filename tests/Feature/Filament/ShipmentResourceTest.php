<?php

use App\Filament\Resources\Shipments\Pages\CreateShipment;
use App\Filament\Resources\Shipments\Pages\ListShipments;
use App\Filament\Widgets\ShipmentStats;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
});

it('lists, searches and filters shipments by order', function () {
    $shipments = Shipment::factory(3)->create();

    Livewire::test(ListShipments::class)
        ->assertCanSeeTableRecords($shipments)
        ->searchTable($shipments->first()->tracking_number)
        ->assertCanSeeTableRecords($shipments->take(1))
        ->assertCanNotSeeTableRecords($shipments->skip(1))
        ->searchTable('')
        ->filterTable('order', $shipments->last()->order_id)
        ->assertCanSeeTableRecords($shipments->take(-1))
        ->assertCanNotSeeTableRecords($shipments->take(2));
});

it('creates a shipment', function () {
    $order = Order::factory()->create();

    Livewire::test(CreateShipment::class)
        ->fillForm(['order_id' => $order->id, 'courier' => 'LBC Express', 'tracking_number' => 'LBC123456'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Shipment::where('tracking_number', 'LBC123456')->exists())->toBeTrue();
});

it('requires a unique tracking number', function () {
    $existing = Shipment::factory()->create();

    Livewire::test(CreateShipment::class)
        ->fillForm(['order_id' => $existing->order_id, 'courier' => 'X', 'tracking_number' => $existing->tracking_number])
        ->call('create')
        ->assertHasFormErrors(['tracking_number' => 'unique']);
});

it('shows the count of unshipped orders in the stats widget', function () {
    Order::factory(2)->paid()->create();
    Order::factory()->shipped()->create();
    Order::factory()->pending()->create();

    Livewire::test(ShipmentStats::class)
        ->assertSee('Unshipped orders')
        ->assertSee('2');

    Livewire::test(ListShipments::class)->assertSeeLivewire(ShipmentStats::class);
});
