<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;

/**
 * @property OrderStatus $status
 */
#[Fillable([
    'user_id',
    'order_number',
    'status',
    'subtotal',
    'discount',
    'coupon_id',
    'coupon_code',
    'tax',
    'shipping_fee',
    'total',
    'shipping_name',
    'shipping_phone',
    'shipping_address_line1',
    'shipping_address_line2',
    'shipping_city',
    'shipping_state',
    'shipping_postal_code',
    'shipping_country',
    'billing_name',
    'billing_address_line1',
    'billing_address_line2',
    'billing_city',
    'billing_state',
    'billing_postal_code',
    'billing_country',
    'idempotency_key',
    'notes',
])]
class Order extends Model implements Commentable
{
    /** @use HasFactory<OrderFactory> */
    use HasComments, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * Get the user that placed the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items for the order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the shipments for the order.
     *
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Get the coupon applied to the order.
     *
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get the status changes for the order, latest first.
     *
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('created_at')->latest('id');
    }

    /**
     * Get the products in the order.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot(['product_name', 'unit_price', 'quantity', 'line_total'])
            ->withTimestamps();
    }

    public const VAT_RATE = 0.12;

    /**
     * Calculate the VAT charged on a subtotal.
     */
    public static function vatFor(float $subtotal): float
    {
        return round($subtotal * self::VAT_RATE, 2);
    }

    /**
     * Place the user's cart as a paid order. Safe to retry: the same idempotency
     * key returns the order it already produced instead of placing another.
     *
     * @param  array<string, string|null>  $billing  Keys: name, address_line1, address_line2, city, state, postal_code, country
     * @param  array<string, string|null>  $shipping  Same keys as $billing
     *
     * @throws \DomainException When the cart is empty or an item is out of stock.
     */
    public static function place(User $user, string $idempotencyKey, array $billing, array $shipping): self
    {
        $existing = fn (): ?self => self::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        try {
            return DB::transaction(function () use ($user, $idempotencyKey, $billing, $shipping, $existing): self {
                if ($order = $existing()) {
                    return $order;
                }

                $cart = self::query()
                    ->where('user_id', $user->id)
                    ->where('status', OrderStatus::Pending)
                    ->lockForUpdate()
                    ->first();

                $items = $cart?->items()->get();

                if ($items === null || $items->isEmpty()) {
                    throw new \DomainException(__('Your cart is empty.'));
                }

                $cart->reserveStock();

                $cart->fill([
                    ...self::addressAttributes('billing', $billing),
                    ...self::addressAttributes('shipping', $shipping),
                    'tax' => self::vatFor((float) $items->sum('line_total')),
                    'status' => OrderStatus::Paid,
                    'idempotency_key' => $idempotencyKey,
                ])->save();

                $cart->recordStatusChange(OrderStatus::Pending, OrderStatus::Paid, $user);

                $cart->recalculateTotals();

                return $cart->refresh();
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request with the same key won the race.
            return $existing() ?? throw $e;
        }
    }

    /**
     * Prefix address fields with their column group (billing_ or shipping_).
     *
     * @param  array<string, string|null>  $address
     * @return array<string, string|null>
     */
    protected static function addressAttributes(string $prefix, array $address): array
    {
        $attributes = [];

        foreach ($address as $field => $value) {
            $attributes["{$prefix}_{$field}"] = $value;
        }

        return $attributes;
    }

    /**
     * Generate a new random order number.
     */
    public static function generateOrderNumber(): string
    {
        return 'ORD-'.strtoupper(Str::random(10));
    }

    /**
     * Get the user's pending order that acts as their cart, creating it if needed.
     */
    public static function cartFor(User $user): self
    {
        return self::query()->firstOrCreate(
            ['user_id' => $user->id, 'status' => OrderStatus::Pending],
            [
                'order_number' => self::generateOrderNumber(),
                'subtotal' => 0,
                'total' => 0,
            ],
        );
    }

    /**
     * Set a product's quantity in the cart, capped at stock. A quantity of zero or less removes it.
     */
    public function setProductQuantity(Product $product, int $quantity): void
    {
        $quantity = min($quantity, $product->stock);

        if ($quantity <= 0) {
            $this->removeProduct($product);

            return;
        }

        $this->items()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'product_name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => $quantity,
                'line_total' => round($product->price * $quantity, 2),
            ],
        );

        $this->recalculateTotals();
    }

    /**
     * Move the order to a new status, refusing invalid transitions, and record who did it.
     * The actor defaults to the authenticated user; null means the system.
     *
     * @throws \DomainException
     */
    public function moveTo(OrderStatus $next, ?User $by = null): void
    {
        $by ??= auth()->user();
        $previous = $this->status;

        DB::transaction(function () use (&$previous, $next, $by): void {
            // Validate against the row as it is now, under lock, so a stale instance
            // cannot repeat a transition (and take or restore stock twice).
            $previous = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail()->status;

            if (! $previous->canMoveTo($next)) {
                throw new \DomainException(__('Cannot move order from :from to :to.', [
                    'from' => $previous->getLabel(),
                    'to' => $next->getLabel(),
                ]));
            }

            $this->status = $previous;
            $this->syncOriginalAttribute('status');

            if ($next === OrderStatus::Paid) {
                $this->reserveStock();
            } elseif ($previous === OrderStatus::Paid && $next === OrderStatus::Cancelled) {
                $this->restoreStock();
            }

            $this->update(['status' => $next]);

            $this->recordStatusChange($previous, $next, $by);
        });

        OrderStatusChanged::dispatch($this, $previous);
    }

    /**
     * Write a status history row. The only place history is created.
     */
    protected function recordStatusChange(?OrderStatus $from, OrderStatus $to, ?User $by = null): OrderStatusHistory
    {
        return $this->statusHistories()->create([
            'user_id' => $by?->getKey(),
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }

    /**
     * Remove a product from the cart.
     */
    public function removeProduct(Product $product): void
    {
        $this->items()->where('product_id', $product->id)->delete();

        $this->recalculateTotals();
    }

    /**
     * Take each item's quantity out of product stock. Call inside a transaction.
     *
     * @throws \DomainException When a product is gone or has too little stock.
     */
    public function reserveStock(): void
    {
        foreach ($this->items()->get() as $item) {
            $product = Product::query()->lockForUpdate()->find($item->product_id);

            if ($product === null || $product->stock < $item->quantity) {
                throw new \DomainException(__(':name is no longer available in the requested quantity.', ['name' => $item->product_name]));
            }

            $product->decrement('stock', $item->quantity);
        }
    }

    /**
     * Put each item's quantity back into product stock. Call inside a transaction.
     */
    public function restoreStock(): void
    {
        foreach ($this->items()->get() as $item) {
            Product::query()->whereKey($item->product_id)->increment('stock', $item->quantity);
        }
    }

    /**
     * Apply a coupon to a pending order, replacing any coupon already applied.
     *
     * @throws \DomainException When the order is not pending, is empty, or the coupon is unknown or unusable.
     */
    public function applyCoupon(Coupon|string $coupon, ?User $by = null): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new \DomainException(__('Coupons can only be applied to pending orders.'));
        }

        if (is_string($coupon)) {
            $coupon = Coupon::findUsableByCode($coupon)
                ?? throw new \DomainException(__('This coupon is invalid or has expired.'));
        } elseif (! $coupon->isUsable()) {
            throw new \DomainException(__('This coupon is invalid or has expired.'));
        }

        if (! $this->items()->exists()) {
            throw new \DomainException(__('Add items to the order before applying a coupon.'));
        }

        $this->update([
            'coupon_id' => $coupon->getKey(),
            'coupon_code' => $coupon->code,
        ]);

        $this->recalculateTotals();
    }

    /**
     * Remove the coupon and its discount from a pending order.
     *
     * @throws \DomainException When the order is not pending.
     */
    public function removeCoupon(): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new \DomainException(__('Coupons can only be removed from pending orders.'));
        }

        $this->update([
            'coupon_id' => null,
            'coupon_code' => null,
            'discount' => '0.00',
        ]);

        $this->recalculateTotals();
    }

    /**
     * Recalculate the subtotal and total from the order items. While pending with a
     * coupon, the discount is the coupon amount capped at the subtotal; otherwise the
     * stored discount stands.
     */
    public function recalculateTotals(): void
    {
        $subtotal = bcadd((string) $this->items()->sum('line_total'), '0', 2);
        $discount = (string) ($this->discount ?? '0');

        if ($this->status === OrderStatus::Pending && $this->coupon_id !== null) {
            // Read fresh so a replaced coupon's amount is never taken from a cached relation.
            $coupon = $this->coupon()->first();
            $couponDiscount = $coupon === null ? '0.00' : bcadd((string) $coupon->discount, '0', 2);
            $discount = bccomp($couponDiscount, $subtotal, 2) === 1 ? $subtotal : $couponDiscount;
        }

        $total = bcadd(bcadd(bcsub($subtotal, $discount, 2), (string) ($this->tax ?? '0'), 2), (string) ($this->shipping_fee ?? '0'), 2);

        $this->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
        ]);
    }
}
