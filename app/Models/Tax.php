<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\UsesClientConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tax in a store's tax master, e.g. "GST 18%" at 18%. Products pick one (products.tax_id)
 * and keep its rate in products.tax_rate, which the price fields, cart and invoices read;
 * changing a tax's rate updates its products.
 */
class Tax extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
    use SoftDeletes;
    use UsesClientConnection;

    protected $table = 'taxes';

    protected $guarded = [];

    /** Taxes are GST: these slabs, keyed by the rate as stored (0 means no tax). */
    public const RATES = [
        '0' => '0% — no tax',
        '0.25' => '0.25%',
        '3' => '3%',
        '5' => '5%',
        '12' => '12%',
        '18' => '18%',
        '28' => '28%',
        '40' => '40%',
    ];

    protected $casts = [
        'rate' => 'float',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('rate')->orderBy('name');
    }

    /** The GST slabs, plus $current when a tax already has another rate (so it still shows). */
    public static function rateOptions(mixed $current = null): array
    {
        $options = self::RATES;
        if (is_numeric($current) && ! isset($options[$key = (string) (float) $current])) {
            $options[$key] = self::rateLabel((float) $current);
            uksort($options, fn ($a, $b) => (float) $a <=> (float) $b);
        }

        return $options;
    }

    /** "18%", "0.25%". */
    public static function rateLabel(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.').'%';
    }

    /** "Cleaning chemicals (18%)", as listed on the product form; "GST 18%" already says its rate. */
    public function getOptionLabelAttribute(): string
    {
        $rate = self::rateLabel((float) $this->rate);

        return str_contains($this->name, $rate) ? $this->name : $this->name.' ('.$rate.')';
    }

    protected static function booted(): void
    {
        static::saved(function (Tax $tax) {
            // Products keep the tax's rate: a new rate moves it, and their saved prices with it.
            if ($tax->wasChanged('rate')) {
                Product::withoutGlobalScopes()->where('tax_id', $tax->getKey())->orderBy('id')
                    ->each(fn (Product $product) => $product->save());
            }
        });

        // A tax deleted for good leaves its products without one: 0%, like any product with no tax.
        static::forceDeleted(function (Tax $tax) {
            Product::withoutGlobalScopes()->where('tax_id', $tax->getKey())->orderBy('id')
                ->each(function (Product $product) {
                    $product->tax_id = null;
                    $product->save();
                });
        });
    }
}
