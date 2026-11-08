<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatusStats extends StatsOverviewWidget
{
    /** Only shown on the Orders list page, not auto-registered on the dashboard. */
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Orders by status';

    protected function getColumns(): int
    {
        return count(OrderStatus::cases());
    }

    protected function getStats(): array
    {
        $counts = Order::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_map(
            fn (OrderStatus $status): Stat => Stat::make($status->getLabel(), (int) ($counts[$status->value] ?? 0))
                ->color($status->getColor()),
            OrderStatus::cases(),
        );
    }
}
