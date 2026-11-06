<?php

use App\Livewire\Cart;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function addToCart(User $user, array $attributes = [], int $quantity = 1): Product
{
    $product = Product::factory()->create(['price' => 10, 'stock' => 10, ...$attributes]);
    $user->cart()->setProductQuantity($product, $quantity);

    return $product;
}

test('guests are redirected to login', function () {
    auth()->logout();

    $this->get(route('cart'))->assertRedirect(route('login'));
});

test('cart shows items with pesos and a total', function () {
    addToCart($this->user, ['name' => 'Lamp', 'price' => 24.5], 2);

    $this->get(route('cart'))->assertOk()->assertSee('Lamp')->assertSee('₱49.00');
});

test('total updates when quantity changes', function () {
    addToCart($this->user, quantity: 1);
    $item = $this->user->cart()->items()->first();

    $cart = Livewire::test(Cart::class);
    expect($cart->html())->toContain('₱10.00');

    $cart->call('increment', $item->id);
    expect($cart->html())->toContain('data-test="summary-total"')->toContain('₱20.00');

    $cart->call('decrement', $item->id);
    expect($cart->html())->not->toContain('₱20.00');
});

test('increment is capped at stock', function () {
    addToCart($this->user, ['stock' => 2], 2);
    $item = $this->user->cart()->items()->first();

    Livewire::test(Cart::class)->call('increment', $item->id);

    expect($item->fresh()->quantity)->toBe(2);
});

test('setting quantity to zero removes the item and updates the total', function () {
    addToCart($this->user);
    addToCart($this->user, ['name' => 'Keep', 'price' => 5]);
    $item = $this->user->cart()->items()->first();

    $cart = Livewire::test(Cart::class)->call('updateQuantity', $item->id, 0);

    expect(OrderItem::find($item->id))->toBeNull()
        ->and($cart->html())->toContain('Keep')->toContain('₱5.00')
        ->and((float) $this->user->cart()->total)->toBe(5.0);
});

test('decrementing from one removes the item', function () {
    addToCart($this->user);
    $item = $this->user->cart()->items()->first();

    Livewire::test(Cart::class)->call('decrement', $item->id)->assertSee('Your cart is empty');
});

test('delete button removes the item', function () {
    addToCart($this->user);
    $item = $this->user->cart()->items()->first();

    Livewire::test(Cart::class)->call('remove', $item->id)->assertSee('Your cart is empty');

    expect($this->user->cart()->items)->toHaveCount(0);
});

test('users cannot change items in another cart', function () {
    $other = User::factory()->create();
    addToCart($other);
    $foreignItem = $other->cart()->items()->first();

    Livewire::test(Cart::class)->call('remove', $foreignItem->id)->assertNotFound();

    expect($foreignItem->fresh())->not->toBeNull();
});

test('items are paginated four per page but the summary covers the whole cart', function () {
    foreach (range(1, 5) as $i) {
        addToCart($this->user);
    }

    Livewire::test(Cart::class)
        ->assertViewHas('items', fn ($items) => $items->count() === 4 && $items->total() === 5)
        ->assertSeeInOrder(['Total', '₱50.00']);
});

test('suggestions exclude cart products and link to product pages', function () {
    $inCart = addToCart($this->user, ['name' => 'InCart']);
    $other = Product::factory()->create(['name' => 'Suggested']);

    Livewire::test(Cart::class)
        ->assertSee('You might also like')
        ->assertSeeHtml('href="'.route('products.show', $other).'"')
        ->assertViewHas('suggestedProducts', fn ($products) => $products->doesntContain($inCart));
});
