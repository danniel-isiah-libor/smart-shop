<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-2">
        <div>
            <flux:button :href="route('cart')" variant="ghost" icon="arrow-left" wire:navigate>{{ __('Back to cart') }}</flux:button>
        </div>
        <div>
            <flux:heading size="xl" level="1">{{ __('Checkout') }}</flux:heading>
            <flux:subheading>{{ __('Enter your billing and shipping details to place your order.') }}</flux:subheading>
        </div>
    </div>

    <div class="flex flex-wrap items-start gap-6">
        <form id="checkout-form" wire:submit="placeOrder" class="flex min-w-0 flex-[999_1_32rem] flex-col gap-6">
            @foreach (['billing' => __('Billing address'), 'shipping' => __('Shipping address')] as $group => $title)
                <flux:card wire:key="{{ $group }}-address" class="flex flex-col gap-5">
                    <flux:heading size="lg">{{ $title }}</flux:heading>

                    @if ($group === 'shipping')
                        <flux:checkbox wire:model.live="sameAsBilling" :label="__('Same as billing address')" />
                    @endif

                    @unless ($group === 'shipping' && $sameAsBilling)
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <flux:input wire:model="{{ $group }}.name" :label="__('Full name')" autocomplete="name" />
                            </div>
                            <div class="sm:col-span-2">
                                <flux:input wire:model="{{ $group }}.address_line1" :label="__('Address line 1')" :placeholder="__('Street address')" />
                            </div>
                            <div class="sm:col-span-2">
                                <flux:input wire:model="{{ $group }}.address_line2" :label="__('Address line 2')" :badge="__('Optional')" :placeholder="__('Apartment, unit, building')" />
                            </div>
                            <flux:input wire:model="{{ $group }}.city" :label="__('City')" />
                            <flux:input wire:model="{{ $group }}.state" :label="__('Province / State')" />
                            <flux:input wire:model="{{ $group }}.postal_code" :label="__('Postal code')" />
                            <flux:select wire:model="{{ $group }}.country" :label="__('Country')">
                                <flux:select.option value="">{{ __('Select country') }}</flux:select.option>
                                <flux:select.option value="Philippines">{{ __('Philippines') }}</flux:select.option>
                            </flux:select>
                        </div>
                    @endunless
                </flux:card>
            @endforeach
        </form>

        <aside aria-label="{{ __('Order summary') }}" class="w-full min-w-0 flex-1 basis-72 md:max-w-sm">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Order summary') }}</flux:heading>

                <div class="flex justify-between text-sm">
                    <flux:text>{{ __('Items') }}</flux:text>
                    <span class="tabular-nums" data-test="summary-units">{{ $summary['units'] }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <flux:text>{{ __('Subtotal') }}</flux:text>
                    <span class="tabular-nums" data-test="summary-subtotal">₱{{ number_format($summary['subtotal'], 2) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <flux:text>{{ __('VAT (12%)') }}</flux:text>
                    <span class="tabular-nums" data-test="summary-vat">₱{{ number_format($summary['vat'], 2) }}</span>
                </div>
                <flux:separator />
                <div class="flex items-baseline justify-between">
                    <flux:heading>{{ __('Total price') }}</flux:heading>
                    <flux:heading size="xl" data-test="summary-total">₱{{ number_format($summary['total'], 2) }}</flux:heading>
                </div>

                @error('order')
                    <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" />
                @enderror

                <flux:button type="submit" form="checkout-form" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="placeOrder">
                    {{ __('Place order') }}
                </flux:button>
            </flux:card>
        </aside>
    </div>
</div>
