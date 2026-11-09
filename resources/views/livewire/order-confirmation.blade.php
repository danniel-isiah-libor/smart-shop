<div class="flex flex-col items-center gap-8">
    <section aria-labelledby="confirmation-title" class="mt-8 flex w-full max-w-2xl flex-col items-center gap-6 text-center">
        <div class="flex size-16 items-center justify-center rounded-full bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
            <flux:icon.check class="size-8" />
        </div>

        <div class="flex flex-col gap-2">
            <flux:heading id="confirmation-title" size="xl" level="1">{{ __('Thank you for your order') }}</flux:heading>
            <flux:subheading>{{ __('Your payment was received and your order is now being processed.') }}</flux:subheading>
            <flux:text>{{ __('Order :number', ['number' => $order->order_number]) }}</flux:text>
        </div>

        <flux:card class="flex w-full flex-col gap-4 text-left">
            <div class="flex items-center justify-between text-sm">
                <flux:text>{{ __('Payment') }}</flux:text>
                <flux:badge color="green" size="sm">{{ __('Paid') }}</flux:badge>
            </div>
            <div class="flex items-center justify-between text-sm">
                <flux:text>{{ __('Order status') }}</flux:text>
                <flux:badge size="sm" data-test="order-status">{{ $order->status->getLabel() }}</flux:badge>
            </div>
            <flux:separator />
            <ul class="flex flex-col gap-3" aria-label="{{ __('Items ordered') }}">
                @foreach ($order->items as $item)
                    <li wire:key="item-{{ $item->id }}" class="flex justify-between gap-4 text-sm">
                        <span class="min-w-0">{{ $item->product_name }} <span class="text-zinc-500">× {{ $item->quantity }}</span></span>
                        <span class="tabular-nums">₱{{ number_format((float) $item->line_total, 2) }}</span>
                    </li>
                @endforeach
            </ul>
            <flux:separator />
            <div class="flex justify-between text-sm">
                <flux:text>{{ __('Subtotal') }}</flux:text>
                <span class="tabular-nums">₱{{ number_format((float) $order->subtotal, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <flux:text>{{ __('VAT (12%)') }}</flux:text>
                <span class="tabular-nums">₱{{ number_format((float) $order->tax, 2) }}</span>
            </div>
            <div class="flex items-baseline justify-between">
                <flux:heading>{{ __('Amount paid') }}</flux:heading>
                <flux:heading size="xl" class="tabular-nums" data-test="amount-paid">₱{{ number_format((float) $order->total, 2) }}</flux:heading>
            </div>
        </flux:card>

        <div class="grid w-full gap-4 text-left sm:grid-cols-2">
            @foreach (['billing' => __('Billing address'), 'shipping' => __('Shipping address')] as $group => $title)
                <flux:card wire:key="{{ $group }}" class="flex flex-col gap-1 text-sm">
                    <flux:heading>{{ $title }}</flux:heading>
                    <flux:text>{{ $order->{$group.'_name'} }}</flux:text>
                    <flux:text>{{ $order->{$group.'_address_line1'} }}</flux:text>
                    @if ($order->{$group.'_address_line2'})
                        <flux:text>{{ $order->{$group.'_address_line2'} }}</flux:text>
                    @endif
                    <flux:text>{{ $order->{$group.'_city'} }}, {{ $order->{$group.'_state'} }} {{ $order->{$group.'_postal_code'} }}</flux:text>
                    <flux:text>{{ $order->{$group.'_country'} }}</flux:text>
                </flux:card>
            @endforeach
        </div>

        <flux:button :href="route('dashboard')" variant="primary" wire:navigate>{{ __('Back to dashboard') }}</flux:button>
    </section>
</div>
