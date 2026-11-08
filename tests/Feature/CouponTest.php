<?php

use App\Models\Coupon;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

it('creates a coupon from the factory with casted attributes', function () {
    $coupon = Coupon::factory()->create(['discount' => 100]);

    expect($coupon->discount)->toBe('100.00')
        ->and($coupon->is_active)->toBeTrue()
        ->and($coupon->expires_at)->toBeInstanceOf(CarbonInterface::class);
});

it('requires unique codes', function () {
    Coupon::factory()->create(['code' => 'SAVE10']);

    Coupon::factory()->create(['code' => 'SAVE10']);
})->throws(QueryException::class);

it('has factory states', function () {
    expect(Coupon::factory()->inactive()->make()->is_active)->toBeFalse()
        ->and(Coupon::factory()->expired()->make()->expires_at->isPast())->toBeTrue();
});

it('authorizes staff via the policy', function () {
    $user = User::factory()->superAdmin()->create();

    expect($user->can('create', Coupon::class))->toBeTrue()
        ->and($user->can('update', Coupon::factory()->create()))->toBeTrue();
});
