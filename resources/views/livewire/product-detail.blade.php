<div class="flex flex-col gap-8">
    <div>
        <flux:button :href="route('dashboard')" variant="ghost" icon="arrow-left" wire:navigate>{{ __('Back to products') }}</flux:button>
    </div>

    <section aria-label="{{ __('Product details') }}" class="flex flex-wrap items-start gap-8">
        @php($images = $product->getMedia(\App\Models\Product::IMAGE_COLLECTION))
        <div class="flex w-full max-w-md flex-1 basis-80 flex-col gap-3" x-data="{ current: @js($images->first()?->getUrl()) }">
            <div class="relative aspect-square overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                @if ($images->isNotEmpty())
                    <img src="{{ $images->first()->getUrl() }}" x-bind:src="current" alt="{{ $product->name }}" class="absolute inset-0 size-full object-cover" />
                @else
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                @endif
            </div>

            @if ($images->count() > 1)
                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Product images') }}">
                    @foreach ($images as $image)
                        <button
                            type="button"
                            wire:key="image-{{ $image->id }}"
                            x-on:click="current = @js($image->getUrl())"
                            aria-label="{{ __('Show image :number', ['number' => $loop->iteration]) }}"
                            class="size-16 overflow-hidden rounded-lg border border-neutral-200 focus-visible:outline-2 focus-visible:outline-offset-2 dark:border-neutral-700"
                        >
                            <img src="{{ $image->getUrl('thumb') }}" alt="" loading="lazy" class="size-full object-cover" />
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex min-w-0 flex-1 basis-80 flex-col gap-5">
            <div class="flex flex-col gap-3">
                <div>
                    <flux:badge size="sm">{{ $product->category->name }}</flux:badge>
                </div>
                <flux:heading size="xl" level="1">{{ $product->name }}</flux:heading>
                <flux:heading size="xl">₱{{ number_format($product->price, 2) }}</flux:heading>
            </div>

            <div class="flex items-center gap-2">
                @if ($product->stock > 0)
                    <flux:badge size="sm" color="green">In stock</flux:badge>
                    <flux:text size="sm">{{ $product->stock }} units available</flux:text>
                @else
                    <flux:badge size="sm" color="red">Out of stock</flux:badge>
                    <flux:text size="sm">{{ __('Currently unavailable') }}</flux:text>
                @endif
            </div>

            <flux:text>{{ $product->description }}</flux:text>

            <flux:separator />

            <div class="flex flex-col gap-2">
                <flux:heading>{{ __('Quantity') }}</flux:heading>
                <div class="flex w-fit items-center gap-2">
                    <flux:button wire:click="decrementQuantity" icon="minus" aria-label="{{ __('Decrease quantity') }}" :disabled="$product->stock <= 0 || $quantity <= 1" />
                    <span class="min-w-10 text-center font-medium tabular-nums" data-test="quantity">{{ $quantity }}</span>
                    <flux:button wire:click="incrementQuantity" icon="plus" aria-label="{{ __('Increase quantity') }}" :disabled="$product->stock <= 0 || $quantity >= $product->stock" />
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <flux:button wire:click="addToCart" icon="shopping-cart" class="flex-1" :disabled="$product->stock <= 0">{{ __('Add to cart') }}</flux:button>
                <flux:button variant="primary" class="flex-1" :disabled="$product->stock <= 0">{{ __('Buy now') }}</flux:button>
            </div>
        </div>
    </section>

    @if ($this->suggestedProducts->isNotEmpty())
        <section aria-label="{{ __('Suggested products') }}" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('More in :category', ['category' => $product->category->name]) }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($this->suggestedProducts as $suggested)
                    <x-product-card wire:key="suggested-{{ $suggested->id }}" :product="$suggested" />
                @endforeach
            </div>
        </section>
    @endif
</div>
