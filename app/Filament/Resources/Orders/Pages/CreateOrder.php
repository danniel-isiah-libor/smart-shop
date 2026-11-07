<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['order_number'] = Order::generateOrderNumber();
        $data['subtotal'] = 0;
        $data['total'] = 0;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotals();
    }
}
