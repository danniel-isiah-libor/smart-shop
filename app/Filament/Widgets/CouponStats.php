<?php

namespace App\Filament\Widgets;

use App\Models\Coupon;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CouponStats extends StatsOverviewWidget
{
    use HasWidgetShield;

    /** Days ahead within which an active coupon counts as nearly expired. */
    private const int NEAR_EXPIRY_DAYS = 7;

    protected function getStats(): array
    {
        $active = Coupon::query()
            ->where('is_active', true)
            ->where(fn($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()));

        $nearExpiry = Coupon::query()
            ->where('is_active', true)
            ->whereBetween('expires_at', [today(), today()->addDays(self::NEAR_EXPIRY_DAYS)]);

        return [
            Stat::make('Active coupons', $active->count())
                ->description('Enabled and not yet expired')
                ->color('success'),
            Stat::make('Near expiry', $nearExpiry->count())
                ->description('Expiring within ' . self::NEAR_EXPIRY_DAYS . ' days')
                ->color('warning'),
        ];
    }
}
