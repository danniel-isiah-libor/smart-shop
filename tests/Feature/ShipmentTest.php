<?php

use App\Models\Order;
use App\Models\Shipment;

it('belongs to an order', function () {
    $shipment = Shipment::factory()->create();

    expect($shipment->order)->toBeInstanceOf(Order::class)
        ->and($shipment->order->shipments)->toHaveCount(1)
        ->and($shipment->shipped_at)->not->toBeNull();
});

it('can be unshipped', function () {
    expect(Shipment::factory()->unshipped()->create()->shipped_at)->toBeNull();
});
