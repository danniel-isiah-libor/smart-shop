<?php

use App\Enums\OrderStatus;
use App\Livewire\Checkout;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function checkoutAddress(array $overrides = []): array
{
    return [
        'name' => 'Juan Dela Cruz',
        'address_line1' => '1 Rizal St',
        'address_line2' => '',
        'city' => 'Manila',
        'state' => 'Metro Manila',
        'postal_code' => '1000',
        'country' => 'Philippines',
        ...$overrides,
    ];
}

function stockedCart(User $user, int $stock = 10, int $quantity = 2, float $price = 100): Product
{
    $product = Product::factory()->create(['price' => $price, 'stock' => $stock]);
    $user->cart()->setProductQuantity($product, $quantity);

    return $product;
}

function fillCheckout($component, bool $same = true)
{
    $component->set('billing', checkoutAddress())->set('sameAsBilling', $same);

    if (! $same) {
        $component->set('shipping', checkoutAddress(['name' => 'Maria Clara', 'city' => 'Cebu']));
    }

    return $component;
}

test('guests are redirected to login', function () {
    auth()->logout();

    $this->get(route('checkout'))->assertRedirect(route('login'));
});

test('an empty cart redirects back to the cart', function () {
    Livewire::test(Checkout::class)->assertRedirect(route('cart'));
});

test('summary shows subtotal, 12% VAT and total', function () {
    stockedCart($this->user, quantity: 2, price: 100);

    Livewire::test(Checkout::class)
        ->assertSeeHtml('₱200.00')
        ->assertSeeHtml('₱24.00')
        ->assertSeeHtml('₱224.00');
});

test('placing an order marks it paid, saves addresses, tax and decrements stock', function () {
    $product = stockedCart($this->user, stock: 10, quantity: 2, price: 100);

    fillCheckout(Livewire::test(Checkout::class), same: false)
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $order = Order::query()->where('user_id', $this->user->id)->sole();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->billing_name)->toBe('Juan Dela Cruz')
        ->and($order->shipping_name)->toBe('Maria Clara')
        ->and($order->shipping_city)->toBe('Cebu')
        ->and((float) $order->subtotal)->toBe(200.0)
        ->and((float) $order->tax)->toBe(24.0)
        ->and((float) $order->total)->toBe(224.0)
        ->and($product->fresh()->stock)->toBe(8);
});

test('same as billing copies billing into shipping', function () {
    stockedCart($this->user);

    fillCheckout(Livewire::test(Checkout::class))->call('placeOrder')->assertHasNoErrors();

    $order = Order::query()->where('user_id', $this->user->id)->sole();

    expect($order->shipping_name)->toBe('Juan Dela Cruz')
        ->and($order->shipping_address_line1)->toBe('1 Rizal St')
        ->and($order->shipping_country)->toBe('Philippines');
});

test('required address fields are validated', function () {
    stockedCart($this->user);

    Livewire::test(Checkout::class)
        ->call('placeOrder')
        ->assertHasErrors(['billing.name', 'billing.address_line1', 'billing.city', 'billing.state', 'billing.postal_code', 'billing.country', 'shipping.name']);

    expect($this->user->cart()->status)->toBe(OrderStatus::Pending);
});

test('shipping is not validated when same as billing', function () {
    stockedCart($this->user);

    fillCheckout(Livewire::test(Checkout::class))->call('placeOrder')->assertHasNoErrors();
});

test('submitting twice with the same key places one order and takes stock once', function () {
    $product = stockedCart($this->user, stock: 10, quantity: 3);

    $component = fillCheckout(Livewire::test(Checkout::class));
    $component->call('placeOrder')->call('placeOrder');

    expect(Order::query()->where('user_id', $this->user->id)->where('status', OrderStatus::Paid)->count())->toBe(1)
        ->and($product->fresh()->stock)->toBe(7);
});

test('a retried key returns the original order without changes', function () {
    $product = stockedCart($this->user, stock: 10, quantity: 3);

    $first = Order::place($this->user, 'key-1', checkoutAddress(), checkoutAddress());
    $second = Order::place($this->user, 'key-1', checkoutAddress(), checkoutAddress());

    expect($second->is($first))->toBeTrue()
        ->and($product->fresh()->stock)->toBe(7);
});

test('a new key on an already placed cart does not place another order', function () {
    stockedCart($this->user);

    Order::place($this->user, 'key-1', checkoutAddress(), checkoutAddress());

    expect(fn () => Order::place($this->user, 'key-2', checkoutAddress(), checkoutAddress()))
        ->toThrow(DomainException::class);

    expect(Order::query()->where('status', OrderStatus::Paid)->count())->toBe(1);
});

test('insufficient stock blocks the order and leaves the cart pending', function () {
    $product = stockedCart($this->user, stock: 5, quantity: 3);
    $product->update(['stock' => 1]);

    fillCheckout(Livewire::test(Checkout::class))
        ->call('placeOrder')
        ->assertHasErrors('order')
        ->assertNoRedirect();

    expect($this->user->cart()->status)->toBe(OrderStatus::Pending)
        ->and($product->fresh()->stock)->toBe(1);
});

test('the cart checkout button links to checkout', function () {
    stockedCart($this->user);

    $this->get(route('cart'))->assertSee(route('checkout'), false);
});

test('the dashboard shows the order placed message', function () {
    $this->withSession(['status' => 'Your order has been placed.'])
        ->get(route('dashboard'))
        ->assertSee('Your order has been placed.');
});

test('placing an order decrements every product exactly once', function () {
    $a = stockedCart($this->user, stock: 10, quantity: 2);
    $b = Product::factory()->create(['price' => 50, 'stock' => 4]);
    $this->user->cart()->setProductQuantity($b, 4);

    $order = Order::place($this->user, 'key-1', checkoutAddress(), checkoutAddress());
    Order::place($this->user, 'key-1', checkoutAddress(), checkoutAddress());

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($a->fresh()->stock)->toBe(8)
        ->and($b->fresh()->stock)->toBe(0);
});

test('a placed order that is later cancelled gives its stock back once', function () {
    $product = stockedCart($this->user, stock: 10, quantity: 3);
    $order = Order::place($this->user, 'key-1', checkoutAddress(), checkoutAddress());

    $order->moveTo(OrderStatus::Cancelled);

    expect($product->fresh()->stock)->toBe(10);

    expect(fn () => $order->refresh()->moveTo(OrderStatus::Cancelled))->toThrow(DomainException::class);
    expect($product->fresh()->stock)->toBe(10);
});

test('placing an order keeps an applied coupon discount', function () {
    stockedCart($this->user, stock: 10, quantity: 2, price: 100);
    $this->user->cart()->applyCoupon(Coupon::factory()->create(['discount' => 50]));

    $order = Order::place($this->user, 'key-1', checkoutAddress(), checkoutAddress());

    expect($order->discount)->toBe('50.00')
        ->and($order->coupon_id)->not->toBeNull()
        ->and($order->total)->toBe('174.00');
});
