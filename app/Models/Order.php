<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = ['status_history' => 'array', 'estimated_delivery' => 'date'];

    /** Ordered fulfilment pipeline used by the tracking timeline. */
    public const STATUSES = [
        'confirmed' => 'Order Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
    ];

    public const PAYMENT_METHODS = [
        'card' => 'Credit / Debit card',
        'upi' => 'UPI',
        'wallet' => 'Wallet',
        'netbanking' => 'Net banking',
        'cod' => 'Cash on delivery',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'ME-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('number', $number)->exists());

        return $number;
    }

    public function statusLabel(): string
    {
        return $this->status === 'cancelled' ? 'Cancelled' : (self::STATUSES[$this->status] ?? ucfirst($this->status));
    }

    /** Index of the current status in the pipeline (-1 when cancelled). */
    public function statusIndex(): int
    {
        $i = array_search($this->status, array_keys(self::STATUSES), true);

        return $i === false ? -1 : $i;
    }

    public function setStatus(string $status, ?string $note = null): void
    {
        $history = $this->status_history ?? [];
        $history[] = ['status' => $status, 'at' => now()->toIso8601String(), 'note' => $note];
        $this->forceFill(['status' => $status, 'status_history' => $history])->save();
    }

    public function shippingAddressLines(): array
    {
        return array_values(array_filter([
            $this->shipping_name,
            $this->shipping_line1,
            $this->shipping_line2,
            trim("{$this->shipping_city}, {$this->shipping_state} {$this->shipping_postal_code}"),
            $this->shipping_country,
        ]));
    }

    public function getUrlAttribute(): string
    {
        return route('orders.confirmation', $this->number);
    }
}
