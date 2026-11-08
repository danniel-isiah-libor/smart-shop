<?php

use App\Livewire\ProductList;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('dashboard renders the product list component', function () {
    $this->get(route('dashboard'))->assertOk()->assertSeeLivewire(ProductList::class);
});

test('products are paginated eight per page', function () {
    Product::factory()->count(10)->create();

    Livewire::test(ProductList::class)->assertViewHas('products', fn ($products) => $products->count() === 8 && $products->total() === 10);
});

test('search filters by name and resets to page one', function () {
    Product::factory()->count(10)->create(['name' => 'Widget']);
    Product::factory()->create(['name' => 'Mouse']);

    Livewire::test(ProductList::class)
        ->call('gotoPage', 2)
        ->set('search', 'Mouse')
        ->assertSet('paginators.page', 1)
        ->assertSee('Mouse')
        ->assertDontSee('Widget');
});

test('category filter limits products', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['name' => 'InCategory', 'category_id' => $category->id]);
    Product::factory()->create(['name' => 'Elsewhere']);

    Livewire::test(ProductList::class)
        ->set('category', (string) $category->id)
        ->assertSee('InCategory')
        ->assertDontSee('Elsewhere');
});

test('stock filter separates in stock and out of stock', function () {
    Product::factory()->create(['name' => 'Available', 'stock' => 5]);
    Product::factory()->create(['name' => 'Gone', 'stock' => 0]);

    Livewire::test(ProductList::class)
        ->set('stock', 'in')->assertSee('Available')->assertDontSee('Gone')
        ->set('stock', 'out')->assertSee('Gone')->assertDontSee('Available');
});

test('empty results show a message and clearing filters restores the list', function () {
    Product::factory()->create(['name' => 'Lamp']);

    Livewire::test(ProductList::class)
        ->set('search', 'zzz')
        ->assertSee('No products found')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSee('Lamp');
});

test('product cards show the product thumbnail', function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
    $this->actingAs(User::factory()->create());
    $product = Product::factory()->create();
    $product->addMedia(UploadedFile::fake()->image('front.png'))->toMediaCollection(Product::IMAGE_COLLECTION);

    Livewire::test(ProductList::class)
        ->assertSeeHtml($product->getFirstMediaUrl(Product::IMAGE_COLLECTION, 'thumb'));
});
