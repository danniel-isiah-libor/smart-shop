<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Order confirmation')]
class OrderConfirmation extends Component
{
    #[Locked]
    public Order $order;

    public function mount(Order $order): void
    {
        abort_unless(
            $order->user_id === auth()->id() && $order->status !== OrderStatus::Pending,
            404,
        );

        $this->order = $order->load('items');
    }

    public function render(): View
    {
        return view('livewire.order-confirmation');
    }
}
