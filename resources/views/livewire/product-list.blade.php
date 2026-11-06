<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search products by name..." />
        </div>

        <flux:select wire:model.live="category" class="md:max-w-48">
            <flux:select.option value="">All categories</flux:select.option>
            @foreach ($categories as $categoryOption)
                <flux:select.option :value="$categoryOption->id">{{ $categoryOption->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="stock" class="md:max-w-48">
            <flux:select.option value="all">All stock</flux:select.option>
            <flux:select.option value="in">In stock</flux:select.option>
            <flux:select.option value="out">Out of stock</flux:select.option>
        </flux:select>

        <flux:button wire:click="clearFilters" variant="ghost" icon="x-mark">Clear filters</flux:button>
    </div>

    @if ($products->isEmpty())
        <div class="flex flex-col items-center gap-3 py-16 text-center">
            <flux:heading size="lg">No products found</flux:heading>
            <flux:button wire:click="clearFilters" variant="filled">Clear filters</flux:button>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($products as $product)
                <x-product-card wire:key="product-{{ $product->id }}" :product="$product" />
            @endforeach
        </div>

        <flux:pagination :paginator="$products" />
    @endif
</div>
