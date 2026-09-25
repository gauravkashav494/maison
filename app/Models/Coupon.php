<?php

namespace App\Models;

use App\Models\Concerns\UsesStoreConnection;
use App\Models\Concerns\BelongsToTemplate;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use UsesStoreConnection;

    use BelongsToTemplate;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public static function findValid(string $code): ?self
    {
        $coupon = static::whereRaw('lower(code) = ?', [strtolower(trim($code))])->first();

        return $coupon && $coupon->isUsable() ? $coupon : null;
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /** Discount amount (in rupees) for a given subtotal; 0 when the minimum is not met. */
    public function discountFor(int $subtotal): int
    {
        if ($subtotal < $this->min_subtotal) {
            return 0;
        }

        return match ($this->type) {
            'percent' => (int) round($subtotal * min(100, $this->value) / 100),
            'fixed' => min($subtotal, $this->value),
            default => 0,
        };
    }

    public function describe(): string
    {
        return match ($this->type) {
            'percent' => "{$this->value}% off",
            'fixed' => money($this->value).' off',
            'free_shipping' => 'Free shipping',
            default => $this->code,
        };
    }
}
