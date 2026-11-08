<?php

use App\Enums\OrderStatus;
use App\Livewire\ProductDetail;
use App\Livewire\ProductList;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('products.show', Product::factory()->create()))->assertRedirect(route('login'));
});

test('product page shows the product with its price in pesos', function () {
    $this->actingAs(User::factory()->create());
    $product = Product::factory()->create(['name' => 'Wireless Mouse', 'price' => 24.99, 'stock' => 5]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('Wireless Mouse')
        ->assertSee('₱24.99')
        ->assertDontSee('$24.99');
});

test('listing cards link to the product page', function () {
    $this->actingAs(User::factory()->create());
    $product = Product::factory()->create();

    Livewire::test(ProductList::class)->assertSeeHtml('href="'.route('products.show', $product).'"');
});

test('adding to cart creates a pending order with the selected quantity', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 10, 'stock' => 5]);

    Livewire::actingAs($user)->test(ProductDetail::class, ['product' => $product])
        ->call('incrementQuantity')
        ->call('addToCart');

    $cart = $user->orders()->where('status', OrderStatus::Pending)->first();

    expect($cart->items)->toHaveCount(1)
        ->and($cart->items->first()->quantity)->toBe(2)
        ->and((float) $cart->total)->toBe(20.0);
});

test('adding again merges quantities and caps at stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 3]);

    Livewire::actingAs($user)->test(ProductDetail::class, ['product' => $product])
        ->set('quantity', 2)->call('addToCart')
        ->set('quantity', 2)->call('addToCart');

    expect($user->cart()->items->first()->quantity)->toBe(3);
});

test('quantity stepper stays between one and stock', function () {
    $this->actingAs(User::factory()->create());
    $product = Product::factory()->create(['stock' => 2]);

    Livewire::test(ProductDetail::class, ['product' => $product])
        ->call('decrementQuantity')->assertSet('quantity', 1)
        ->call('incrementQuantity')->call('incrementQuantity')->assertSet('quantity', 2);
});

test('out of stock products cannot be added to the cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 0]);

    Livewire::actingAs($user)->test(ProductDetail::class, ['product' => $product])
        ->assertSee('Out of stock')
        ->assertSeeHtml('disabled')
        ->call('addToCart');

    expect($user->cart()->items)->toHaveCount(0);
});

test('suggested products share the category, exclude the current one and are clickable', function () {
    $this->actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Current']);
    $related = Product::factory()->create(['category_id' => $category->id, 'name' => 'Related']);
    Product::factory()->create(['name' => 'Elsewhere']);

    Livewire::test(ProductDetail::class, ['product' => $product])
        ->assertSee('Related')
        ->assertDontSee('Elsewhere')
        ->assertSeeHtml('href="'.route('products.show', $related).'"');
});

test('product page shows the uploaded product images', function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
    $this->actingAs(User::factory()->create());
    $product = Product::factory()->create();
    $product->addMedia(UploadedFile::fake()->image('front.jpg'))->toMediaCollection(Product::IMAGE_COLLECTION);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee($product->getFirstMediaUrl(Product::IMAGE_COLLECTION), false);
});
