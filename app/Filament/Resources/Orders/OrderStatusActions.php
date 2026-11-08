<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class OrderStatusActions
{
    /**
     * One confirmable action per status move, each shown only when the move is allowed.
     *
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            self::move('markAsPaid', 'Mark as paid', OrderStatus::Paid, 'success', 'heroicon-o-banknotes'),
            self::move('ship', 'Ship', OrderStatus::Shipped, 'warning', 'heroicon-o-truck'),
            self::move('markAsDelivered', 'Mark as delivered', OrderStatus::Delivered, 'success', 'heroicon-o-check-circle'),
            self::move('cancel', 'Cancel', OrderStatus::Cancelled, 'danger', 'heroicon-o-x-circle'),
        ];
    }

    private static function move(string $name, string $label, OrderStatus $target, string $color, string $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->icon($icon)
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->status->canMoveTo($target))
            ->action(function (Order $record) use ($target, $label): void {
                try {
                    $record->moveTo($target);
                } catch (\DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__(':action: done', ['action' => $label]))->success()->send();
            });
    }
}
