<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\UsesClientConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    protected $casts = ['value' => 'float', 'min_subtotal' => 'float', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

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

    /** Discount amount (in rupees and paise) for a given subtotal; 0 when the minimum is not met. */
    public function discountFor(float $subtotal): float
    {
        if ($subtotal < $this->min_subtotal) {
            return 0.0;
        }

        return match ($this->type) {
            'percent' => round($subtotal * min(100, $this->value) / 100, 2),
            'fixed' => min($subtotal, $this->value),
            default => 0.0,
        };
    }

    public function describe(): string
    {
        return match ($this->type) {
            'percent' => rtrim(rtrim(number_format($this->value, 2, '.', ''), '0'), '.').'% off',
            'fixed' => money($this->value).' off',
            'free_shipping' => 'Free shipping',
            default => $this->code,
        };
    }
}
