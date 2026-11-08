<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderStatusUpdatedNotification implements ShouldQueue
{
    public function handle(OrderStatusChanged $event): void
    {
        $event->order->user->notify(new OrderStatusUpdated($event->order));
    }
}
