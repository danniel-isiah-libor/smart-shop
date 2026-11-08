<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
{
    public function __construct(public Order $order) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $status = $this->order->status;

        $message = (new MailMessage)
            ->subject(__('Order :number is now :status', ['number' => $number, 'status' => $status->getLabel()]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]));

        if ($status === OrderStatus::Cancelled) {
            $message->line(__('We\'re sorry, but your order :number has been cancelled.', ['number' => $number]))
                ->line(__('If this wasn\'t expected or you have any questions, just reply to this email and we\'ll be happy to help. We hope to see you again soon!'));
        } else {
            $message->line(__('Good news! Your order :number is now :status.', ['number' => $number, 'status' => $status->getLabel()]));
        }

        return $message->action(__('View your orders'), route('dashboard'));
    }
}
