<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\UsesClientConnection;
use App\Models\Concerns\BelongsToTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use UsesClientConnection;

    use BelongsToClient;

    use BelongsToTemplate;

    protected $guarded = [];

    protected $casts = [
        'status_history' => 'array',
        'estimated_delivery' => 'date',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** Ordered fulfilment pipeline used by the tracking timeline. */
    public const STATUSES = [
        'confirmed' => 'Order Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Dispatched',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
    ];

    /** Dispatch is the "shipped" step of the pipeline; fulfilment is handled by the client. */
    public const STATUS_DISPATCHED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    /** Cancelled sits outside the pipeline and ends the order. */
    public const STATUS_CANCELLED = 'cancelled';

    /** Every status an order can be in, for filters and labels. */
    public static function statusOptions(): array
    {
        return self::STATUSES + [self::STATUS_CANCELLED => 'Cancelled'];
    }

    public const PAYMENT_METHODS = [
        'card' => 'Credit / Debit card',
        'upi' => 'UPI',
        'wallet' => 'Wallet',
        'netbanking' => 'Net banking',
        'cod' => 'Cash on delivery',
    ];

    /** The storefront account that placed this, or null for a guest. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Legacy link to the pre-split users table; kept so historic rows stay readable. */
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

    // ---- Query scopes used by the admin list filters -----------------------

    /** Orders placed on one calendar day. */
    public function scopePlacedOn($query, $date)
    {
        return $query->when(filled($date), fn ($q) => $q->whereDate('created_at', $date));
    }

    /** Orders placed within a range; either end may be left open. */
    public function scopePlacedBetween($query, $from = null, $until = null)
    {
        return $query
            ->when(filled($from), fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when(filled($until), fn ($q) => $q->whereDate('created_at', '<=', $until));
    }

    public function scopeWithStatus($query, $status = null)
    {
        return $query->when(filled($status), fn ($q) => $q->where('status', $status));
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Statuses this order may move to next.
     *
     * The pipeline only ever moves forward: a cancelled or delivered order is finished, an
     * order can be cancelled up to the moment it is delivered, and a step cannot be skipped
     * backwards. This is the single definition of what is allowed — the admin actions, the
     * bulk actions and any future API all ask this.
     *
     * @return array<int,string>
     */
    public function allowedTransitions(): array
    {
        if ($this->isCancelled() || $this->isDelivered()) {
            return [];
        }

        $pipeline = array_keys(self::STATUSES);
        $index = $this->statusIndex();
        $next = $index === -1 ? $pipeline : array_slice($pipeline, $index + 1);

        return [...$next, self::STATUS_CANCELLED];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Records a status change, with who made it and when, and refuses a move the order is not
     * allowed to make. Returns false instead of throwing so a caller can report it.
     */
    public function transitionTo(string $status, ?string $note = null, mixed $by = null): bool
    {
        if (! $this->canTransitionTo($status)) {
            return false;
        }

        $this->setStatus($status, $note, $by);

        return true;
    }

    /** Writes the status, the audit entry and the matching timestamp. */
    public function setStatus(string $status, ?string $note = null, mixed $by = null): void
    {
        $history = $this->status_history ?? [];
        $history[] = array_filter([
            'status' => $status,
            'at' => now()->toIso8601String(),
            'note' => $note,
            'by' => $by ? trim(($by->name ?? 'Staff').' <'.($by->email ?? '').'>') : null,
            'by_id' => $by->id ?? null,
            'by_type' => $by ? $by::class : null,
        ], fn ($value) => $value !== null);

        $attributes = ['status' => $status, 'status_history' => $history];
        $attributes += match ($status) {
            self::STATUS_DISPATCHED => ['dispatched_at' => now()],
            self::STATUS_DELIVERED => ['delivered_at' => now()],
            self::STATUS_CANCELLED => ['cancelled_at' => now(), 'cancellation_reason' => $note],
            default => [],
        };

        $this->forceFill($attributes)->save();
    }

    /** The audit trail, newest last, ready to display. */
    public function statusTimeline(): array
    {
        return array_map(fn (array $entry) => $entry + [
            'label' => self::statusOptions()[$entry['status']] ?? ucfirst((string) $entry['status']),
        ], $this->status_history ?? []);
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
