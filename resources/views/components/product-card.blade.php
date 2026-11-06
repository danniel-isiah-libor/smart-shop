@props(['product'])

<a
    href="{{ route('products.show', $product) }}"
    wire:navigate
    {{ $attributes->class('group block rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-900 dark:focus-visible:outline-zinc-100') }}
>
    <flux:card class="flex h-full flex-col gap-3 overflow-hidden !p-0 transition group-hover:border-zinc-400 dark:group-hover:border-zinc-500">
        <div class="relative h-36 overflow-hidden border-b border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>

        <div class="flex flex-1 flex-col gap-2 px-5 pb-5">
            <div>
                <flux:badge size="sm">{{ $product->category->name }}</flux:badge>
            </div>
            <flux:heading size="lg">{{ $product->name }}</flux:heading>
            <flux:heading size="lg" class="mt-auto">₱{{ number_format($product->price, 2) }}</flux:heading>
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
</a>
