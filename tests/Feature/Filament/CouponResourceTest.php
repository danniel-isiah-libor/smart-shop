<?php

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists coupons', function () {
    $coupons = Coupon::factory(3)->create();

    Livewire::test(ListCoupons::class)
        ->assertCanSeeTableRecords($coupons)
        ->searchTable($coupons->first()->code)
        ->assertCanSeeTableRecords($coupons->take(1));
});

it('filters coupons by active status', function () {
    $active = Coupon::factory()->create();
    $inactive = Coupon::factory()->inactive()->create();

    Livewire::test(ListCoupons::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('creates a coupon', function () {
    Livewire::test(CreateCoupon::class)
        ->fillForm(['code' => 'WELCOME', 'discount' => 100, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Coupon::where('code', 'WELCOME')->exists())->toBeTrue();
});

it('rejects a duplicate code', function () {
    Coupon::factory()->create(['code' => 'DUPE']);

    Livewire::test(CreateCoupon::class)
        ->fillForm(['code' => 'DUPE', 'discount' => 10])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);
});
