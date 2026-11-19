<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\UsesClientConnection;
use App\Support\TaxBreakdown;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LogicException;

class Order extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    protected $casts = [
        'status_history' => 'array',
        'shipping_cost' => 'float',
        'subtotal' => 'float',
        'discount' => 'float',
        'tax' => 'float',
        'tax_rate' => 'float',
        'prices_include_tax' => 'boolean',
        'total' => 'float',
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

    /**
     * Temporary override: when non-empty, checkout in every store offers only these methods,
     * whatever the store's own settings say. Empty it to go back to the per-store settings.
     * The other methods' code (views, admin, labels) is kept as is.
     */
    public const CHECKOUT_ONLY_PAYMENT_METHODS = ['cod'];

    /** Payment methods a shopper can pick at checkout: code => label. */
    public static function checkoutPaymentMethods(): array
    {
        $enabled = self::CHECKOUT_ONLY_PAYMENT_METHODS
            ?: (array) setting('checkout.payment_methods', array_keys(self::PAYMENT_METHODS));

        return array_filter(self::PAYMENT_METHODS, fn ($k) => in_array($k, $enabled, true), ARRAY_FILTER_USE_KEY);
    }

    /** The storefront account that placed this, or null for a guest. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** The sales person who placed this order (for themselves or for the customer), if a sales person did. */
    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'salesperson_id')->withTrashed();
    }

    /** Placed by a sales person: no delivery, the store's own address is on the order. */
    public function isSalesOrder(): bool
    {
        return $this->salesperson_id !== null;
    }

    /** Legacy link to the pre-split users table; kept so historic rows stay readable. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->withTrashed();
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'ME-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('number', $number)->exists());

        return $number;
    }

    /** Payment status as shoppers see it on the storefront: "Pay on delivery" for COD. */
    public function paymentStatusLabel(): string
    {
        return $this->payment_status === 'cod' ? 'Pay on delivery' : ucfirst((string) $this->payment_status);
    }

    /** Filament colour for the payment status badge. */
    public function paymentStatusColor(): string
    {
        return match ($this->payment_status) {
            'paid' => 'success',
            'cod' => 'warning',
            'refunded' => 'gray',
            default => 'danger',
        };
    }

    /** Filament colour for the order status badge. */
    public function statusColor(): string
    {
        return match ($this->status) {
            'confirmed' => 'info',
            'processing' => 'warning',
            self::STATUS_DISPATCHED, 'out_for_delivery' => 'primary',
            self::STATUS_DELIVERED => 'success',
            self::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
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

    /** Orders placed within a range; either end may be left open. */
    public function scopePlacedBetween($query, $from = null, $until = null)
    {
        return $query
            ->when(filled($from), fn ($q) => $q->whereDate('created_at', '>=', Carbon::parse($from)))
            ->when(filled($until), fn ($q) => $q->whereDate('created_at', '<=', Carbon::parse($until)));
    }

    public function scopeWithStatus($query, $status = null)
    {
        return $query->when(filled($status), fn ($q) => $q->where('status', $status));
    }

    /**
     * Received but not yet handled: still at the first step of the pipeline. It stops being new
     * as soon as staff move it on (processing, dispatched, delivered) or cancel it.
     */
    public function isNew(): bool
    {
        return $this->status === array_key_first(self::STATUSES);
    }

    /** Orders that are new (see isNew()): the ones the sidebar badge counts. */
    public function scopeAwaitingAction($query)
    {
        return $query->where('status', array_key_first(self::STATUSES));
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

    /**
     * Statuses from which the customer may still cancel the order themselves, from their
     * account. Once it is dispatched it is in the courier's hands, so only the store can stop it.
     */
    public const CUSTOMER_CANCELLABLE = ['confirmed', 'processing'];

    public function customerCanCancel(): bool
    {
        return in_array($this->status, self::CUSTOMER_CANCELLABLE, true)
            && $this->canTransitionTo(self::STATUS_CANCELLED);
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

        // A cancelled order is finished: its goods are already back on the shelf, so it is never
        // reopened (Order::allowedTransitions()).
        if ($this->isCancelled() && $status !== self::STATUS_CANCELLED) {
            throw new LogicException("Order {$this->number} is cancelled and cannot be reopened.");
        }

        $this->getConnection()->transaction(function () use ($attributes, $status) {
            $wasCancelled = $this->isCancelled();

            // Cancelling puts the goods back on the shelf. Every status change comes through
            // here, so the stock moves back exactly once, whoever made the change.
            $this->forceFill($attributes)->save();

            if (! $wasCancelled && $status === self::STATUS_CANCELLED) {
                $this->returnStock();
            }
        });
    }

    /** Puts every line's quantity back into the product's stock. */
    private function returnStock(): void
    {
        foreach ($this->items()->with('product')->get() as $item) {
            $item->product?->adjustStock((int) $item->qty, (array) $item->options, StockMovement::CANCELLED, $this);
        }
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
            // A store address kept on a sales order may have no city or PIN code.
            trim(implode(', ', array_filter([$this->shipping_city, trim("{$this->shipping_state} {$this->shipping_postal_code}")]))),
            $this->shipping_country,
        ]));
    }

    /**
     * Link to download this order's invoice (PDF). Signed and short-lived, so it also works for a
     * guest on the tracking page; the order's own customer, or the guest who has just placed it,
     * can open the plain link too (InvoiceController).
     */
    public function invoiceUrl(): string
    {
        return URL::temporarySignedRoute('orders.invoice', now()->addHours(2), ['number' => $this->number]);
    }

    /**
     * Gives the order the next invoice number in its store's series, once; later calls return
     * the number it already has.
     *
     * One unbroken series per store and Indian financial year (April–March), as GST asks:
     * "<prefix>/<yy-yy>/<0001>", e.g. INV/26-27/0001, at most 16 characters. The prefix is the
     * store's (Site settings → Checkout → Invoice). New orders are numbered as they are placed;
     * an older order gets its number the first time its invoice is opened.
     */
    public function assignInvoiceNumber(): string
    {
        if (filled($this->invoice_number)) {
            return $this->invoice_number;
        }

        $placed = $this->created_at ?? now();
        $start = $placed->month >= 4 ? $placed->year : $placed->year - 1;
        $fy = sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
        $prefix = strtoupper((string) setting('checkout.invoice_prefix', 'INV')) ?: 'INV';

        return $this->getConnection()->transaction(function () use ($fy, $prefix) {
            // Re-read under a lock: another request may have numbered this order, or taken the
            // next number, a moment ago. The unique index backs this up.
            $fresh = static::withoutGlobalScopes()->lockForUpdate()->find($this->getKey());
            if ($fresh && filled($fresh->invoice_number)) {
                return $this->invoice_number = $fresh->invoice_number;
            }

            $seq = 1 + (int) static::withoutGlobalScopes()->withTrashed()
                ->where('client_id', $this->client_id)->where('invoice_fy', $fy)
                ->lockForUpdate()->max('invoice_seq');
            $number = $prefix.'/'.$fy.'/'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            static::withoutGlobalScopes()->whereKey($this->getKey())
                ->update(['invoice_number' => $number, 'invoice_fy' => $fy, 'invoice_seq' => $seq]);
            $this->forceFill(['invoice_number' => $number, 'invoice_fy' => $fy, 'invoice_seq' => $seq])->syncOriginalAttributes(['invoice_number', 'invoice_fy', 'invoice_seq']);

            return $number;
        });
    }

    public function getUrlAttribute(): string
    {
        return route('orders.confirmation', $this->number);
    }

    /** Label for this order's tax row, as it applied when the order was placed. */
    public function taxLine(): string
    {
        return tax_line($this->tax_label ?: 'Tax', $this->tax_rate, (bool) $this->prices_include_tax);
    }

    /**
     * The tax per rate, as charged when the order was placed: from each line's own rate and tax,
     * or, for orders placed before lines had them, the order's single rate. "CGST + SGST" gives a
     * CGST and an SGST row per rate.
     *
     * @return list<array{label: string, rate: float, amount: float, taxable: float}>
     */
    public function taxRows(): array
    {
        $label = $this->tax_label ?: 'Tax';
        $items = $this->items->filter(fn (OrderItem $item) => $item->tax_rate !== null);

        if ($items->isEmpty() || $items->count() !== $this->items->count()) {
            $tax = (float) $this->tax;

            return TaxBreakdown::rows([[
                'rate' => (float) $this->tax_rate,
                'tax' => $tax,
                'taxable' => $this->prices_include_tax ? max(0, (float) $this->subtotal - (float) $this->discount - $tax) : max(0, (float) $this->subtotal - (float) $this->discount),
            ]], $label);
        }

        // The taxable value per line is worked out as at checkout (its share of the discount);
        // the tax is the amount each line was charged.
        $items = $items->values();
        $lines = TaxBreakdown::for(
            $items->map(fn (OrderItem $item) => ['amount' => (float) $item->total, 'rate' => (float) $item->tax_rate])->all(),
            (float) $this->discount,
            (bool) $this->prices_include_tax,
        )['lines'];

        $byRate = [];
        foreach ($items as $i => $item) {
            $key = number_format((float) $item->tax_rate, 2, '.', '');
            $byRate[$key] ??= ['rate' => (float) $item->tax_rate, 'tax' => 0.0, 'taxable' => 0.0];
            $byRate[$key]['tax'] = round($byRate[$key]['tax'] + (float) $item->tax, 2);
            $byRate[$key]['taxable'] = round($byRate[$key]['taxable'] + $lines[$i]['taxable'], 2);
        }
        ksort($byRate, SORT_NUMERIC);

        return TaxBreakdown::rows(array_values($byRate), $label);
    }
}
