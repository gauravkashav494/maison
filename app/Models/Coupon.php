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

    protected $casts = ['value' => 'float', 'min_subtotal' => 'float', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'usage_limit' => 'integer', 'per_customer_limit' => 'integer', 'used_count' => 'integer'];

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

    /**
     * Usable at all, and not yet used as often as allowed by this customer: their account, or the
     * phone number on the order (a guest, or the same person with a second account). With neither
     * known (a shopper not signed in), only the overall rules apply; the order checks again.
     */
    public function isUsableBy(?int $customerId, ?string $phone): bool
    {
        if (! $this->isUsable()) {
            return false;
        }
        if ($this->per_customer_limit === null || ($customerId === null && blank($phone))) {
            return true;
        }

        return $this->timesUsedBy($customerId, $phone) < $this->per_customer_limit;
    }

    /** This customer's orders (by account or phone) that used this code; cancelled orders don't count. */
    public function timesUsedBy(?int $customerId, ?string $phone): int
    {
        return Order::query()
            ->whereRaw('lower(coupon_code) = ?', [strtolower($this->code)])
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->where(fn ($q) => $q
                ->when(filled($phone), fn ($q) => $q->orWhere('phone', $phone))
                ->when($customerId !== null, fn ($q) => $q->orWhere('customer_id', $customerId)))
            ->count();
    }

    /** Whether a cart of this subtotal reaches the coupon's minimum order (every coupon type, free shipping included). */
    public function meetsMinimum(float $subtotal): bool
    {
        return $subtotal >= (float) $this->min_subtotal;
    }

    /** Discount amount (in rupees and paise) for a given subtotal; 0 when the minimum is not met. */
    public function discountFor(float $subtotal): float
    {
        if (! $this->meetsMinimum($subtotal)) {
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
        return static::describeOffer($this->type, (float) $this->value) ?? $this->code;
    }

    /**
     * "10% off", "₹200.00 off" or "Free shipping" for a code's type and value; also used for the
     * copy an order keeps of the code it was placed with. Null for an unknown type.
     */
    public static function describeOffer(?string $type, ?float $value): ?string
    {
        return match ($type) {
            'percent' => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').'% off',
            'fixed' => money((float) $value).' off',
            'free_shipping' => 'Free shipping',
            default => null,
        };
    }
}
