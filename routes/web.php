<?php

use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\ProductController;
use App\Livewire\Cart;
use App\Livewire\Checkout;
use App\Livewire\OrderConfirmation;
use App\Livewire\ProductDetail;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('/products', [ProductController::class, 'index'])
        ->name('products.index');

    Route::livewire('/products/{product}', ProductDetail::class)->name('products.show');

    Route::livewire('/cart', Cart::class)->name('cart');

    Route::livewire('/checkout', Checkout::class)->name('checkout');

    Route::livewire('/orders/{order}/confirmation', OrderConfirmation::class)->name('orders.confirmation');
});

require __DIR__.'/settings.php';
