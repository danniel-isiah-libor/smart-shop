<?php

use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('/products', [ProductController::class, 'index'])
        ->name('products.index');
});

require __DIR__.'/settings.php';
