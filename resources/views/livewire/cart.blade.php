<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-2">
        <div>
            <flux:button :href="route('dashboard')" variant="ghost" icon="arrow-left" wire:navigate>{{ __('Back to products') }}</flux:button>
        </div>
        <div>
            <flux:heading size="xl" level="1">{{ __('Cart') }}</flux:heading>
            <flux:subheading>{{ trans_choice(':count item in your cart.|:count items in your cart.', $summary['units']) }}</flux:subheading>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="flex flex-col items-center gap-3 py-16 text-center">
            <flux:heading size="lg">{{ __('Your cart is empty') }}</flux:heading>
            <flux:button :href="route('dashboard')" variant="filled" wire:navigate>{{ __('Browse products') }}</flux:button>
        </div>
    @else
        <div class="flex flex-wrap items-start gap-6">
            <section aria-label="{{ __('Cart items') }}" class="flex min-w-0 flex-[999_1_32rem] flex-col gap-4">
                <flux:card class="divide-y divide-zinc-200 !p-0 dark:divide-zinc-700">
                    @foreach ($items as $item)
                        <div wire:key="item-{{ $item->id }}" class="flex flex-wrap items-center gap-4 p-4">
                            <div class="relative size-20 shrink-0 overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                            </div>

                            <div class="flex min-w-40 flex-1 flex-col gap-1">
                                <flux:heading>
                                    <a href="{{ route('products.show', $item->product_id) }}" wire:navigate class="hover:underline">{{ $item->product_name }}</a>
                                </flux:heading>
                                <flux:text size="sm">₱{{ number_format($item->unit_price, 2) }} {{ __('each') }}</flux:text>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:button size="sm" icon="minus" wire:click="decrement({{ $item->id }})" aria-label="{{ __('Decrease quantity') }}" />
                                <span class="min-w-8 text-center text-sm font-medium tabular-nums">{{ $item->quantity }}</span>
                                <flux:button size="sm" icon="plus" wire:click="increment({{ $item->id }})" aria-label="{{ __('Increase quantity') }}" :disabled="$item->quantity >= $item->product->stock" />
                            </div>

                            <div class="w-28 text-end font-semibold tabular-nums">₱{{ number_format($item->line_total, 2) }}</div>

                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="remove({{ $item->id }})" aria-label="{{ __('Remove :name from cart', ['name' => $item->product_name]) }}" />
                        </div>
                    @endforeach
                </flux:card>

                <flux:pagination :paginator="$items" />
            </section>

            <aside aria-label="{{ __('Order summary') }}" class="w-full min-w-0 flex-1 basis-72 md:max-w-sm">
                <flux:card class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ __('Order summary') }}</flux:heading>
                    <div class="flex justify-between text-sm">
                        <flux:text>{{ __('Items') }}</flux:text>
                        <span class="tabular-nums" data-test="summary-units">{{ $summary['units'] }}</span>
                    </div>
                    <flux:separator />
                    <div class="flex items-baseline justify-between">
                        <flux:heading>{{ __('Total') }}</flux:heading>
                        <flux:heading size="xl" data-test="summary-total">₱{{ number_format($summary['total'], 2) }}</flux:heading>
                    </div>
                    <flux:button variant="primary" class="w-full" :href="route('checkout')" wire:navigate>{{ __('Checkout') }}</flux:button>
                </flux:card>
            </aside>
        </div>
    @endif

    @if ($suggestedProducts->isNotEmpty())
        <section aria-label="{{ __('Suggested products') }}" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('You might also like') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($suggestedProducts as $suggested)
                    <x-product-card wire:key="suggested-{{ $suggested->id }}" :product="$suggested" />
                @endforeach
            </div>
        </section>
    @endif
</div>
