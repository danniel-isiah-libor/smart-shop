<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
#[Fillable(['code', 'discount', 'expires_at', 'is_active'])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount' => 'decimal:2',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether the coupon is active and not expired. It is valid through the end of its expiry date.
     */
    public function isUsable(): bool
    {
        return $this->is_active
            && ($this->expires_at === null || ! $this->expires_at->copy()->endOfDay()->isPast());
    }

    /**
     * Find an active, unexpired coupon by its code.
     */
    public static function findUsableByCode(string $code): ?self
    {
        $coupon = self::query()->where('code', trim($code))->first();

        return $coupon?->isUsable() ? $coupon : null;
    }
}
