<?php

namespace App\Livewire;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Checkout')]
class Checkout extends Component
{
    /** @var array<string, string> */
    public array $billing = [];

    /** @var array<string, string> */
    public array $shipping = [];

    public bool $sameAsBilling = false;

    public string $idempotencyKey = '';

    public function mount(): void
    {
        if ($this->cart()->items()->doesntExist()) {
            $this->redirectRoute('cart', navigate: true);

            return;
        }

        $this->billing = $this->blankAddress();
        $this->shipping = $this->blankAddress();
        $this->idempotencyKey = (string) Str::uuid();
    }

    public function placeOrder(): void
    {
        $this->validate($this->rules());

        $shipping = $this->sameAsBilling ? $this->billing : $this->shipping;

        try {
            Order::place(auth()->user(), $this->idempotencyKey, $this->billing, $shipping);
        } catch (\DomainException $e) {
            $this->addError('order', $e->getMessage());

            return;
        }

        session()->flash('status', __('Your order has been placed.'));

        $this->redirectRoute('dashboard', navigate: true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        $rules = $this->addressRules('billing');

        if (! $this->sameAsBilling) {
            $rules += $this->addressRules('shipping');
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function addressRules(string $group): array
    {
        return [
            "{$group}.name" => ['required', 'string', 'max:255'],
            "{$group}.address_line1" => ['required', 'string', 'max:255'],
            "{$group}.address_line2" => ['nullable', 'string', 'max:255'],
            "{$group}.city" => ['required', 'string', 'max:255'],
            "{$group}.state" => ['required', 'string', 'max:255'],
            "{$group}.postal_code" => ['required', 'string', 'max:20'],
            "{$group}.country" => ['required', 'in:Philippines'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function blankAddress(): array
    {
        return [
            'name' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => '',
        ];
    }

    protected function cart(): Order
    {
        return auth()->user()->cart();
    }

    /**
     * @return array{units: int, subtotal: float, vat: float, total: float}
     */
    public function summary(): array
    {
        $items = $this->cart()->items();
        $subtotal = (float) $items->sum('line_total');
        $vat = Order::vatFor($subtotal);

        return [
            'units' => (int) $items->sum('quantity'),
            'subtotal' => $subtotal,
            'vat' => $vat,
            'total' => $subtotal + $vat,
        ];
    }

    public function render(): View
    {
        return view('livewire.checkout', ['summary' => $this->summary()]);
    }
}
