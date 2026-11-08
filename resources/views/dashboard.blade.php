<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
        @endif

        <div>
            <flux:heading size="xl" level="1">{{ __('Products') }}</flux:heading>
            <flux:subheading>{{ __('Browse, search and filter your product catalog.') }}</flux:subheading>
        </div>

        <livewire:product-list />
    </div>
</x-layouts::app>
