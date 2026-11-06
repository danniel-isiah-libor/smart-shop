<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class LandingPageController extends Controller
{
    /**
     * Show the public landing page with the latest available products.
     */
    public function __invoke(): View
    {
        $products = Product::query()
            ->with('category')
            ->where('stock', '>', 0)
            ->latest()
            ->take(5)
            ->get();

        return view('landing', ['products' => $products]);
    }
}
