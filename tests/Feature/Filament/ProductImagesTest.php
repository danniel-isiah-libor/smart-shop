<?php

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
    $this->actingAs(User::factory()->superAdmin()->create());
});

function productFormData(array $images): array
{
    return [
        'category_id' => Category::factory()->create()->id,
        'name' => 'Mug',
        'description' => 'A mug',
        'price' => 9.99,
        'stock' => 3,
        'images' => $images,
    ];
}

it('stores uploaded images in the products collection', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(productFormData([
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.png'),
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('name', 'Mug')->firstOrFail();

    expect($product->getMedia(Product::IMAGE_COLLECTION))->toHaveCount(2)
        ->and($product->getFirstMedia(Product::IMAGE_COLLECTION)->hasGeneratedConversion('thumb'))->toBeTrue();
});

it('rejects more than five images', function () {
    $images = array_map(fn (int $i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 6));

    Livewire::test(CreateProduct::class)
        ->fillForm(productFormData($images))
        ->call('create')
        ->assertHasFormErrors(['images']);
});

it('rejects disallowed file types', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(productFormData([UploadedFile::fake()->image('a.gif')]))
        ->call('create')
        ->assertHasFormErrors(['images']);
});

it('caps each image at 2 MB', function () {
    $field = Livewire::test(CreateProduct::class)->assertFormFieldExists('images')->instance()->getSchema('form')->getComponent('images');

    expect($field->getMaxSize())->toBe(2048)
        ->and($field->getMaxFiles())->toBe(5);
});

it('shows products with a thumbnail column', function () {
    $product = Product::factory()->create();

    Livewire::test(ListProducts::class)
        ->assertCanSeeTableRecords([$product])
        ->assertTableColumnExists('images');
});
