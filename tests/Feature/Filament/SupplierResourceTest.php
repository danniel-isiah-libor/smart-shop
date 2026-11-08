<?php

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
});

it('lists and searches suppliers', function () {
    $suppliers = Supplier::factory(3)->create();

    Livewire::test(ListSuppliers::class)
        ->assertCanSeeTableRecords($suppliers)
        ->searchTable($suppliers->first()->name)
        ->assertCanSeeTableRecords($suppliers->take(1));
});

it('creates a supplier', function () {
    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'Acme Trading', 'email' => 'acme@example.com', 'phone' => '+63 917 123 4567'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Supplier::where('email', 'acme@example.com')->exists())->toBeTrue();
});

it('requires a unique email', function () {
    $existing = Supplier::factory()->create();

    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'Dup', 'email' => $existing->email])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('has many products', function () {
    $supplier = Supplier::factory()->hasProducts(2)->create();

    expect($supplier->products)->toHaveCount(2);
});
