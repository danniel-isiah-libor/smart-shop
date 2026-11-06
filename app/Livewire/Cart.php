<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Cart')]
class Cart extends Component
{
    use WithPagination;

    public function increment(int $itemId): void
    {
        $item = $this->findItem($itemId);

        $this->changeQuantity($item, $item->quantity + 1);
    }

    public function decrement(int $itemId): void
    {
        $item = $this->findItem($itemId);

        $this->changeQuantity($item, $item->quantity - 1);
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        $this->changeQuantity($this->findItem($itemId), $quantity);
    }

    public function remove(int $itemId): void
    {
        $this->changeQuantity($this->findItem($itemId), 0);
    }

    protected function cart(): Order
    {
        return auth()->user()->cart();
    }

    protected function findItem(int $itemId): OrderItem
    {
        return $this->cart()->items()->with('product')->findOrFail($itemId);
    }

    protected function changeQuantity(OrderItem $item, int $quantity): void
    {
        $this->cart()->setProductQuantity($item->product, $quantity);
    }

    /**
     * @return LengthAwarePaginator<int, OrderItem>
     */
    public function items(): LengthAwarePaginator
    {
        $items = $this->cart()->items()->with('product')->orderBy('id')->paginate(4);

        if ($items->isEmpty() && $items->currentPage() > 1) {
            $this->previousPage();

            return $this->cart()->items()->with('product')->orderBy('id')->paginate(4);
        }

        return $items;
    }

    /**
     * @return array{units: int, total: float}
     */
    public function summary(): array
    {
        $cart = $this->cart();

        return [
            'units' => (int) $cart->items()->sum('quantity'),
            'total' => (float) $cart->items()->sum('line_total'),
        ];
    }

    /**
     * @return Collection<int, Product>
     */
    public function suggestedProducts(): Collection
    {
        return Product::query()
            ->with('category')
            ->whereNotIn('id', $this->cart()->items()->pluck('product_id'))
            ->inRandomOrder()
            ->limit(4)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.cart', [
            'items' => $this->items(),
            'summary' => $this->summary(),
            'suggestedProducts' => $this->suggestedProducts(),
        ]);
    }
}
