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
                <flux:card wire:key="product-{{ $product->id }}" class="flex flex-col gap-3 overflow-hidden !p-0">
                    <div class="relative h-36 overflow-hidden border-b border-neutral-200 dark:border-neutral-700">
                        <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    <div class="flex flex-1 flex-col gap-2 px-5 pb-5">
                        <div>
                            <flux:badge size="sm">{{ $product->category->name }}</flux:badge>
                        </div>
                        <flux:heading size="lg">{{ $product->name }}</flux:heading>
                        <flux:heading size="lg" class="mt-auto">${{ number_format($product->price, 2) }}</flux:heading>
                        <div class="flex items-center gap-2">
                            @if ($product->stock > 0)
                                <flux:badge size="sm" color="green">In stock</flux:badge>
                            @else
                                <flux:badge size="sm" color="red">Out of stock</flux:badge>
                            @endif
                            <flux:text size="sm">{{ $product->stock }} units</flux:text>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <flux:pagination :paginator="$products" />
    @endif
</div>
