<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class OrderCouponActions
{
    /**
     * Apply and remove coupon actions, both shown only while the order is pending.
     *
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            self::apply(),
            self::remove(),
        ];
    }

    private static function apply(): Action
    {
        return Action::make('applyCoupon')
            ->label('Apply coupon')
            ->color('gray')
            ->icon('heroicon-o-ticket')
            ->authorize('update')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
            ->schema([
                Select::make('coupon_id')
                    ->label('Coupon')
                    ->options(fn (): array => Coupon::query()
                        ->orderBy('code')
                        ->get()
                        ->filter(fn (Coupon $coupon): bool => $coupon->isUsable())
                        ->pluck('code', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data, Order $record): void {
                $coupon = Coupon::find($data['coupon_id']);

                try {
                    if ($coupon === null) {
                        throw new \DomainException(__('Coupon not found.'));
                    }

                    $record->applyCoupon($coupon);
                } catch (\DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('Coupon applied'))->success()->send();
            });
    }

    private static function remove(): Action
    {
        return Action::make('removeCoupon')
            ->label('Remove coupon')
            ->color('danger')
            ->icon('heroicon-o-x-mark')
            ->authorize('update')
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending && $record->coupon_id !== null)
            ->action(function (Order $record): void {
                try {
                    $record->removeCoupon();
                } catch (\DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('Coupon removed'))->success()->send();
            });
    }
}
