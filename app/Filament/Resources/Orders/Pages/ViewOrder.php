<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderCouponActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\OrderStatusActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...OrderStatusActions::make(),
            ...OrderCouponActions::make(),
            EditAction::make(),
            CommentsAction::make(),
        ];
    }
}
