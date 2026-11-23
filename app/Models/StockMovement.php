<?php

namespace App\Models;

use App\Clients\ClientContext;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\UsesClientConnection;
use App\Support\Activity\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One move in or out of a product's stock (Inventory → Stock movements): a sale, a cancelled
 * order coming back, stock received, a stock count… with the stock left after it.
 *
 * Written only by Product (adjustStock() and the product form's saves), never edited, so the
 * history cannot be rewritten. It has no LogsActivity on purpose: the staff action behind a
 * movement writes its own activity line (App\Services\Inventory, or the product's own "changed
 * the stock" line), and a second "added stock movement" line would only repeat it.
 */
class StockMovement extends Model
{
    use BelongsToTemplate;
    use UsesClientConnection;

    // Taken off the shelf.
    public const SALE = 'sale';

    public const DAMAGED = 'damaged';

    // Put back on the shelf.
    public const CANCELLED = 'cancelled';

    public const RECEIVED = 'received';

    public const RETURNED = 'returned';

    // Either way.
    public const OPENING = 'opening';

    public const COUNT = 'count';

    public const EDITED = 'edited';

    /** Why the stock moved, as shown in the history. */
    public const REASONS = [
        self::SALE => 'Sold',
        self::CANCELLED => 'Order cancelled',
        self::RECEIVED => 'Stock received',
        self::RETURNED => 'Customer return',
        self::DAMAGED => 'Damaged or lost',
        self::COUNT => 'Stock count',
        self::OPENING => 'Opening stock',
        self::EDITED => 'Edited on product',
    ];

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'quantity' => 'integer',
        'stock_after' => 'integer',
    ];

    protected static function booted(): void
    {
        // The client of the product, whatever the request: a storefront sale has a client but no
        // signed-in staff, and a locked staff session always writes into its own client.
        static::creating(function (StockMovement $movement) {
            $context = app(ClientContext::class);
            if ($context->isResolved() && $context->id() && ($context->isLocked() || blank($movement->client_id))) {
                $movement->client_id = $context->id();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst((string) $this->reason);
    }

    /** "Alpha Coat (M / Red)". */
    public function itemName(): string
    {
        return $this->product_name.($this->variant ? ' ('.$this->variant.')' : '');
    }

    /** Writes one movement of $product's stock; nothing when the stock did not change (except a count). */
    public static function log(Product $product, int $quantity, int $stockAfter, string $reason, ?array $options = null, ?Order $order = null, ?string $note = null): ?self
    {
        if ($quantity === 0 && $reason !== self::COUNT) {
            return null;
        }

        return static::create([
            'client_id' => $product->client_id,
            'template' => $product->template,
            'product_id' => $product->getKey(),
            'product_name' => (string) $product->name,
            'variant' => $options ? implode(' / ', array_values($options)) : null,
            'options' => $options ?: null,
            'quantity' => $quantity,
            'stock_after' => max(0, $stockAfter),
            'reason' => $reason,
            'note' => filled($note) ? mb_substr(trim((string) $note), 0, 255) : null,
            'order_id' => $order?->getKey(),
            'order_number' => $order?->number,
            'by_name' => app(ActivityLog::class)->actor()?->name,
        ]);
    }
}
