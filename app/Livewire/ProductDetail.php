<?php

namespace App\Livewire;

use App\Models\Product;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class ProductDetail extends Component
{
    public Product $product;

    public int $quantity = 1;

    public function mount(Product $product): void
    {
        $this->product = $product->load(['category', 'media']);
    }

    public function incrementQuantity(): void
    {
        $this->quantity = min($this->quantity + 1, max($this->product->stock, 1));
    }

    public function decrementQuantity(): void
    {
        $this->quantity = max($this->quantity - 1, 1);
    }

    public function addToCart(): void
    {
        $product = $this->product->fresh();

        if ($product === null || $product->stock <= 0) {
            return;
        }

        $cart = auth()->user()->cart();
        $existing = (int) $cart->items()->where('product_id', $product->id)->value('quantity');

        $cart->setProductQuantity($product, $existing + $this->quantity);

        $this->product = $product->load(['category', 'media']);
        $this->quantity = 1;

        Flux::toast(text: __('Added to your cart.'), variant: 'success');
    }

    /**
     * @return Collection<int, Product>
     */
    public function getSuggestedProductsProperty(): Collection
    {
        return Product::query()
            ->with(['category', 'media'])
            ->where('category_id', $this->product->category_id)
            ->whereKeyNot($this->product->id)
            ->latest('id')
            ->limit(4)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.product-detail')->title($this->product->name);
    }
}
