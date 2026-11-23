<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Support\Activity\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Inventory: staff stock adjustments (stock received, returns, damage, stock counts) and the
 * store's low-stock level. Each adjustment moves the stock through Product::adjustStock(), so it
 * lands in the stock history like a sale, and writes one activity-log line saying what was done.
 */
class Inventory
{
    /** What a member of staff can record, and what it does to the stock. */
    public const ADJUSTMENTS = [
        StockMovement::RECEIVED => 'Stock received (adds)',
        StockMovement::RETURNED => 'Customer return (adds)',
        StockMovement::DAMAGED => 'Damaged or lost (removes)',
        StockMovement::COUNT => 'Stock count (sets the exact number)',
    ];

    public const DEFAULT_LOW_STOCK = 5;

    public const STATUS_OUT = 'out';

    public const STATUS_LOW = 'low';

    public const STATUS_IN = 'in';

    public const STATUSES = [
        self::STATUS_OUT => 'Out of stock',
        self::STATUS_LOW => 'Low stock',
        self::STATUS_IN => 'In stock',
    ];

    public function __construct(private readonly ActivityLog $log) {}

    /** At or below this many, an item counts as low on stock. */
    public static function lowStockLevel(): int
    {
        return max(0, (int) Setting::get('inventory.low_stock_level', self::DEFAULT_LOW_STOCK));
    }

    public function setLowStockLevel(int $level): void
    {
        $old = self::lowStockLevel();
        $level = max(0, $level);
        Setting::set('inventory', ['low_stock_level' => $level]);

        if ($old !== $level) {
            $this->log->record(
                'updated',
                "changed the low-stock level from {$old} to {$level}",
                changes: [['field' => 'low_stock_level', 'label' => 'low-stock level', 'old' => (string) $old, 'new' => (string) $level]],
                area: 'Inventory',
                subjectName: 'Inventory settings',
                subjectType: 'Settings',
            );
        }
    }

    /**
     * Out of stock (nothing left), low (the product, or one of its variants, is at or below the
     * low-stock level) or in stock.
     */
    public static function status(Product $product): string
    {
        $level = self::lowStockLevel();

        return match (true) {
            (int) $product->stock <= 0 => self::STATUS_OUT,
            (int) ($product->lowest_stock ?? $product->stock) <= $level => self::STATUS_LOW,
            default => self::STATUS_IN,
        };
    }

    /** Limits a product query to one status (as status() reads it). */
    public static function whereStatus(Builder $query, string $status): Builder
    {
        $level = self::lowStockLevel();
        $stock = $query->qualifyColumn('stock');
        $lowest = 'COALESCE('.$query->qualifyColumn('lowest_stock').', '.$stock.')';

        return match ($status) {
            self::STATUS_OUT => $query->where($stock, '<=', 0),
            self::STATUS_LOW => $query->where($stock, '>', 0)->whereRaw($lowest.' <= ?', [$level]),
            self::STATUS_IN => $query->where($stock, '>', 0)->whereRaw($lowest.' > ?', [$level]),
            default => $query,
        };
    }

    /**
     * The variants to pick from when adjusting: options as JSON => "M / Red · 3 in stock".
     *
     * @return array<string, string>
     */
    public static function variantOptions(Product $product): array
    {
        $options = [];
        foreach ($product->sellsVariants() ? $product->variantList() : [] as $variant) {
            $options[json_encode($variant['options'])] = $variant['label'].' · '.$variant['stock'].' in stock'.($variant['is_active'] ? '' : ' (not on sale)');
        }

        return $options;
    }

    /**
     * Records an adjustment by staff and returns the movement (null when nothing changed).
     *
     * @param  string|null  $variant  the variant's options as JSON (variantOptions()); required for a product sold by variant
     *
     * @throws InvalidArgumentException for an unknown adjustment, a missing variant, a negative quantity or
     *                                  writing off more than is in stock; the message is for the person
     */
    public function adjust(Product $product, ?string $variant, string $type, int $quantity, ?string $note = null): ?StockMovement
    {
        if (! isset(self::ADJUSTMENTS[$type]) || $quantity < 0 || ($quantity === 0 && $type !== StockMovement::COUNT)) {
            throw new InvalidArgumentException('Choose what happened and enter how many.');
        }

        $product->refresh(); // the stock as it is now, not as the screen showed it
        $options = $variant !== null ? (array) json_decode($variant, true) : [];
        [$index, $options] = $product->variantIndex($options);
        if ($product->sellsVariants() && $index === null) {
            throw new InvalidArgumentException('Choose which variant to adjust.');
        }

        $before = $index !== null ? (int) ($product->variants[$index]['stock'] ?? $product->stock) : (int) $product->stock;
        if ($type === StockMovement::DAMAGED && $quantity > $before) {
            throw new InvalidArgumentException($before === 0 ? 'There is no stock to write off.' : "There are only {$before} in stock.");
        }

        $delta = match ($type) {
            StockMovement::DAMAGED => -$quantity,
            StockMovement::COUNT => $quantity - $before,
            default => $quantity,
        };

        if ($delta === 0) {
            // A count that matches the shelf still goes in the history: the stock was checked.
            $movement = StockMovement::log($product, 0, $before, $type, $options, note: $note);
            $after = $before;
        } else {
            $after = $product->adjustStock($delta, $options ?? [], $type, note: $note);
            $movement = StockMovement::where('product_id', $product->getKey())->latest('id')->first();
        }

        $this->logAdjustment($product, $options, $type, abs($delta), $before, $after);

        return $movement;
    }

    private function logAdjustment(Product $product, ?array $options, string $type, int $units, int $before, int $after): void
    {
        $item = $this->log->describer()->reference($product).($options ? ' ('.implode(' / ', $options).')' : '');
        $count = $units.' '.Str::plural('unit', $units);

        $description = match ($type) {
            StockMovement::RECEIVED => "received {$count} of {$item}",
            StockMovement::RETURNED => "took back {$count} of {$item} from a customer return",
            StockMovement::DAMAGED => "wrote off {$count} of {$item} as damaged or lost",
            default => "counted the stock of {$item}",
        }.($before === $after ? ", stock stays {$after}" : ", stock {$before} → {$after}");

        $this->log->record(
            'updated',
            $description,
            $product,
            changes: [['field' => 'stock', 'label' => 'stock'.($options ? ' ('.implode(' / ', $options).')' : ''), 'old' => (string) $before, 'new' => (string) $after]],
            area: 'Inventory',
        );
    }
}
