<?php

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * A pending cart with one product line of the given unit price and quantity.
 *
 * @param  array<string, mixed>  $attributes
 */
function couponCart(float $price = 100, int $quantity = 3, array $attributes = []): Order
{
    $user = User::factory()->create();
    $cart = Order::factory()->for($user)->pending()->create($attributes);
    $product = Product::factory()->create(['price' => $price, 'stock' => 50]);
    $cart->setProductQuantity($product, $quantity);

    return $cart->refresh();
}

test('a valid coupon sets the coupon, the discount and a recalculated total', function () {
    $order = couponCart(price: 100, quantity: 3);
    $coupon = Coupon::factory()->create(['code' => 'SAVE50', 'discount' => 50]);

    $order->applyCoupon($coupon);

    $order->refresh();
    expect($order->coupon_id)->toBe($coupon->id)
        ->and($order->coupon_code)->toBe('SAVE50')
        ->and($order->discount)->toBe('50.00')
        ->and($order->subtotal)->toBe('300.00')
        ->and($order->total)->toBe('250.00')
        ->and($order->coupon->is($coupon))->toBeTrue();
});

test('a coupon can be applied by code', function () {
    $order = couponCart();
    $coupon = Coupon::factory()->create(['code' => 'BYCODE', 'discount' => 25]);

    $order->applyCoupon('BYCODE');

    expect($order->fresh()->coupon_id)->toBe($coupon->id)
        ->and($order->fresh()->discount)->toBe('25.00');
});

test('tax and shipping stay on top of the discounted subtotal', function () {
    $order = couponCart(price: 100, quantity: 2, attributes: ['tax' => 24, 'shipping_fee' => 10]);

    $order->applyCoupon(Coupon::factory()->create(['discount' => 50]));

    expect($order->fresh()->total)->toBe('184.00');
});

test('a coupon expiring today is still usable', function () {
    $order = couponCart();
    $coupon = Coupon::factory()->create(['expires_at' => now()->startOfDay()]);

    $order->applyCoupon($coupon);

    expect($order->fresh()->coupon_id)->toBe($coupon->id);
});

test('a coupon without an expiry date is usable', function () {
    $order = couponCart();
    $coupon = Coupon::factory()->create(['expires_at' => null]);

    $order->applyCoupon($coupon);

    expect($order->fresh()->coupon_id)->toBe($coupon->id);
});

test('invalid coupons are refused and change nothing', function (Closure $coupon) {
    $order = couponCart(price: 100, quantity: 2);
    $before = $order->fresh()->only(['coupon_id', 'coupon_code', 'discount', 'total']);

    expect(fn () => $order->applyCoupon($coupon()))->toThrow(DomainException::class);

    expect($order->fresh()->only(['coupon_id', 'coupon_code', 'discount', 'total']))->toBe($before);
})->with([
    'inactive' => fn () => Coupon::factory()->inactive()->create(),
    'expired' => fn () => Coupon::factory()->expired()->create(),
    'unknown code' => fn () => 'NOPE',
    'empty code' => fn () => '',
]);

test('a coupon cannot be applied to an empty cart', function () {
    $order = Order::factory()->pending()->create();
    $coupon = Coupon::factory()->create(['discount' => 50]);

    expect(fn () => $order->applyCoupon($coupon))->toThrow(DomainException::class);

    expect($order->fresh()->coupon_id)->toBeNull()
        ->and($order->fresh()->discount)->toBe('0.00');
});

test('a discount larger than the subtotal is capped and the total is never negative', function () {
    $order = couponCart(price: 100, quantity: 1);

    $order->applyCoupon(Coupon::factory()->create(['discount' => 500]));

    expect($order->fresh()->discount)->toBe('100.00')
        ->and($order->fresh()->total)->toBe('0.00');
});

test('a discount equal to the subtotal leaves tax and shipping payable', function () {
    $order = couponCart(price: 100, quantity: 1, attributes: ['tax' => 12, 'shipping_fee' => 8]);

    $order->applyCoupon(Coupon::factory()->create(['discount' => 100]));

    expect($order->fresh()->discount)->toBe('100.00')
        ->and($order->fresh()->total)->toBe('20.00');
});

test('changing the cart quantity re-caps the discount', function () {
    $order = couponCart(price: 100, quantity: 3);
    $product = $order->items()->firstOrFail()->product;
    $order->applyCoupon(Coupon::factory()->create(['discount' => 250]));

    expect($order->fresh()->discount)->toBe('250.00');

    $order->setProductQuantity($product, 2);

    expect($order->fresh()->subtotal)->toBe('200.00')
        ->and($order->fresh()->discount)->toBe('200.00')
        ->and($order->fresh()->total)->toBe('0.00');

    $order->setProductQuantity($product, 4);

    expect($order->fresh()->discount)->toBe('250.00')
        ->and($order->fresh()->total)->toBe('150.00');
});

test('removing the coupon returns the original total', function () {
    $order = couponCart(price: 100, quantity: 3, attributes: ['tax' => 36]);
    $original = $order->fresh()->total;
    $order->applyCoupon(Coupon::factory()->create(['discount' => 50]));

    $order->refresh()->removeCoupon();

    $order->refresh();
    expect($order->coupon_id)->toBeNull()
        ->and($order->coupon_code)->toBeNull()
        ->and($order->discount)->toBe('0.00')
        ->and($order->total)->toBe($original);
});

test('applying a second coupon replaces the first', function () {
    $order = couponCart(price: 100, quantity: 3);
    $first = Coupon::factory()->create(['discount' => 50]);
    $second = Coupon::factory()->create(['code' => 'SECOND', 'discount' => 80]);

    $order->applyCoupon($first);
    $order->refresh()->applyCoupon($second);

    expect($order->fresh()->coupon_id)->toBe($second->id)
        ->and($order->fresh()->coupon_code)->toBe('SECOND')
        ->and($order->fresh()->discount)->toBe('80.00')
        ->and($order->fresh()->total)->toBe('220.00');
});

test('a failed replacement keeps the first coupon', function () {
    $order = couponCart(price: 100, quantity: 3);
    $first = Coupon::factory()->create(['discount' => 50]);
    $order->applyCoupon($first);

    expect(fn () => $order->refresh()->applyCoupon(Coupon::factory()->inactive()->create()))
        ->toThrow(DomainException::class);

    expect($order->fresh()->coupon_id)->toBe($first->id)
        ->and($order->fresh()->discount)->toBe('50.00');
});

test('fractional discounts are calculated without float drift', function () {
    $order = couponCart(price: 0.1, quantity: 3);

    $order->applyCoupon(Coupon::factory()->create(['discount' => 0.2]));

    expect($order->fresh()->subtotal)->toBe('0.30')
        ->and($order->fresh()->discount)->toBe('0.20')
        ->and($order->fresh()->total)->toBe('0.10');
});

test('a coupon cannot be applied to an order that is not pending', function (string $state) {
    $order = Order::factory()->{$state}()->create();
    $coupon = Coupon::factory()->create();

    expect(fn () => $order->applyCoupon($coupon))->toThrow(DomainException::class);

    expect($order->fresh()->coupon_id)->toBeNull();
})->with(['paid', 'shipped', 'delivered', 'cancelled']);

test('a coupon cannot be removed from an order that is not pending', function () {
    $order = couponCart(price: 100, quantity: 2);
    $order->applyCoupon(Coupon::factory()->create(['discount' => 50]));
    $order->refresh()->moveTo(OrderStatus::Paid);

    expect(fn () => $order->refresh()->removeCoupon())->toThrow(DomainException::class);

    expect($order->fresh()->discount)->toBe('50.00')
        ->and($order->fresh()->coupon_id)->not->toBeNull();
});

test('a paid order keeps its discount when totals are recalculated', function () {
    $order = couponCart(price: 100, quantity: 2);
    $coupon = Coupon::factory()->create(['discount' => 50]);
    $order->applyCoupon($coupon);
    $order->refresh()->moveTo(OrderStatus::Paid);

    $coupon->update(['discount' => 150]);
    $order->refresh()->recalculateTotals();

    expect($order->fresh()->discount)->toBe('50.00')
        ->and($order->fresh()->total)->toBe('150.00');
});

test('a coupon that expires before payment does not change the stored discount', function () {
    $order = couponCart(price: 100, quantity: 2);
    $coupon = Coupon::factory()->create(['discount' => 50]);
    $order->applyCoupon($coupon);

    $coupon->update(['expires_at' => now()->subDay()]);
    $order->refresh()->moveTo(OrderStatus::Paid);

    expect($order->fresh()->discount)->toBe('50.00')
        ->and($order->fresh()->total)->toBe('150.00');
});

test('deleting the coupon keeps the code and discount on the order', function () {
    $order = couponCart(price: 100, quantity: 2);
    $coupon = Coupon::factory()->create(['code' => 'GONE10', 'discount' => 50]);
    $order->applyCoupon($coupon);
    $order->refresh()->moveTo(OrderStatus::Paid);

    $coupon->delete();

    $order->refresh();
    expect($order->coupon_id)->toBeNull()
        ->and($order->coupon_code)->toBe('GONE10')
        ->and($order->discount)->toBe('50.00')
        ->and($order->total)->toBe('150.00');
});

test('applying or removing a coupon writes no status history', function () {
    $order = couponCart();

    $order->applyCoupon(Coupon::factory()->create(['discount' => 10]));
    $order->refresh()->removeCoupon();

    expect($order->statusHistories()->count())->toBe(0);
});

test('the orders table has coupon columns', function () {
    expect(Schema::hasColumns('orders', ['coupon_id', 'coupon_code']))->toBeTrue();
});

test('the order factory can attach a coupon', function () {
    $coupon = Coupon::factory()->create(['code' => 'FACTORY']);

    $order = Order::factory()->withCoupon($coupon)->create();
    $random = Order::factory()->withCoupon()->create();

    expect($order->coupon_id)->toBe($coupon->id)
        ->and($order->coupon_code)->toBe('FACTORY')
        ->and($random->coupon_id)->not->toBeNull()
        ->and($random->coupon_code)->toBe($random->coupon->code);
});

test('a coupon is usable only while active and not expired', function () {
    expect(Coupon::factory()->create()->isUsable())->toBeTrue()
        ->and(Coupon::factory()->create(['expires_at' => null])->isUsable())->toBeTrue()
        ->and(Coupon::factory()->create(['expires_at' => now()->startOfDay()])->isUsable())->toBeTrue()
        ->and(Coupon::factory()->inactive()->create()->isUsable())->toBeFalse()
        ->and(Coupon::factory()->expired()->create()->isUsable())->toBeFalse();
});

test('findUsableByCode returns only usable coupons', function () {
    $usable = Coupon::factory()->create(['code' => 'GOOD']);
    Coupon::factory()->inactive()->create(['code' => 'OFF']);
    Coupon::factory()->expired()->create(['code' => 'OLD']);

    expect(Coupon::findUsableByCode('GOOD')?->is($usable))->toBeTrue()
        ->and(Coupon::findUsableByCode('OFF'))->toBeNull()
        ->and(Coupon::findUsableByCode('OLD'))->toBeNull()
        ->and(Coupon::findUsableByCode('MISSING'))->toBeNull();
});
