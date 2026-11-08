<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class ShopStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $revenue = Order::query()
            ->where('status', OrderStatus::Paid)
            ->sum('total');

        return [
            Stat::make('Revenue (paid orders)', Number::currency((float) $revenue, 'PHP'))
                ->color('success'),
            Stat::make('Orders today', Order::query()->whereDate('created_at', today())->count()),
            Stat::make('Pending orders', Order::query()->where('status', OrderStatus::Pending)->count())
                ->color('warning'),
        ];
    }
}
