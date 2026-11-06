<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $stock = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedStock(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category', 'stock');
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function getProductsProperty(): LengthAwarePaginator
    {
        return Product::query()
            ->with('category')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->category !== '', fn ($query) => $query->where('category_id', $this->category))
            ->when($this->stock === 'in', fn ($query) => $query->where('stock', '>', 0))
            ->when($this->stock === 'out', fn ($query) => $query->where('stock', 0))
            ->latest('id')
            ->paginate(8);
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategoriesProperty(): Collection
    {
        return Category::orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.product-list', [
            'products' => $this->products,
            'categories' => $this->categories,
        ]);
    }
}
