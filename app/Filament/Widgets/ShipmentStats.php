<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShipmentStats extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected function getStats(): array
    {
        return [
            Stat::make('Unshipped orders', Order::query()->where('status', OrderStatus::Paid)->count())
                ->description('Paid and awaiting shipment')
                ->color('warning'),
        ];
    }
}
