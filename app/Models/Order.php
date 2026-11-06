<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'order_number',
    'status',
    'subtotal',
    'discount',
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
    'notes',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

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

    /**
     * Get the user's pending order that acts as their cart, creating it if needed.
     */
    public static function cartFor(User $user): self
    {
        return self::query()->firstOrCreate(
            ['user_id' => $user->id, 'status' => OrderStatus::Pending],
            [
                'order_number' => 'ORD-'.strtoupper(Str::random(10)),
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
     * Remove a product from the cart.
     */
    public function removeProduct(Product $product): void
    {
        $this->items()->where('product_id', $product->id)->delete();

        $this->recalculateTotals();
    }

    /**
     * Recalculate the subtotal and total from the order items.
     */
    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('line_total');

        $this->update([
            'subtotal' => $subtotal,
            'total' => $subtotal - $this->discount + $this->tax + $this->shipping_fee,
        ]);
    }
}
