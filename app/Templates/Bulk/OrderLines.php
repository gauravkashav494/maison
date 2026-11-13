<?php

namespace App\Templates\Bulk;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Turns products into the lines a bulk buyer orders: one line per thing that has its own
 * code. A product sold by variant becomes one line per active variant, with that variant's
 * code, price and stock. A product whose options are plain lists (sizes, colours) becomes one
 * line per combination, so nothing has to be chosen on a separate page. A product with
 * nothing to choose is a single line.
 *
 * Every line carries exactly the options the shared cart endpoint expects, so adding a line
 * is the same POST /cart/items the other templates make.
 */
class OrderLines
{
    /**
     * The shared cart accepts at most this many of a line per request
     * (see CartController::store and ::update), so no box offers more.
     */
    public const MAX_PER_LINE = 20;

    /** A product with more combinations than this is ordered from its own page instead. */
    public const MAX_COMBINATIONS = 24;

    /**
     * @return array<int, array{key:string, product_id:int, name:string, label:?string, options:array<string,string>, sku:?string, price:int, price_formatted:string, stock:int, max:int, url:string, unit:?string, image:?string, category:?string}>
     */
    public static function for(Product $product): array
    {
        $lines = [];
        $attributes = $product->variantAttributes();
        // Only a choice the customer actually makes is worth naming on the line.
        $choices = array_column(array_filter($attributes, fn (array $a) => count($a['values']) > 1), 'name');

        if ($product->sellsVariants()) {
            foreach ($product->variantList() as $variant) {
                if ($variant['is_active']) {
                    $lines[] = self::line($product, $choices, $variant['options'], $variant['sku'], $variant['price'], $variant['stock']);
                }
            }

            return $lines;
        }

        $combinations = self::combinations($attributes);
        if ($combinations === null) {
            return [];
        }

        foreach ($combinations as $options) {
            $lines[] = self::line($product, $choices, $options, $product->sku, (int) $product->price, (int) $product->stock);
        }

        return $lines;
    }

    /**
     * Lines for many products, in order.
     *
     * @param  iterable<Product>  $products
     */
    public static function forMany(iterable $products): Collection
    {
        return collect($products)->flatMap(fn (Product $product) => self::for($product))->values();
    }

    /**
     * The quick-order lookup: every line by key, and every code (lower-cased) to the keys
     * that carry it. A code shared by several lines — variants that never got codes of their
     * own fall back to the product's — is ambiguous, and the pad says so.
     *
     * @return array{lines: array<string, array>, codes: array<string, array<int, string>>}
     */
    public static function index(Collection $lines): array
    {
        $codes = [];
        foreach ($lines as $line) {
            if (filled($line['sku'])) {
                $codes[mb_strtolower($line['sku'])][] = $line['key'];
            }
        }

        return [
            'lines' => $lines->keyBy('key')->all(),
            'codes' => $codes,
        ];
    }

    /**
     * Every combination of the attribute values, or null when there are too many to list.
     *
     * @return array<int, array<string,string>>|null
     */
    private static function combinations(array $attributes): ?array
    {
        $combinations = [[]];

        foreach ($attributes as $attribute) {
            $next = [];
            foreach ($combinations as $options) {
                foreach ($attribute['values'] as $value) {
                    $next[] = $options + [$attribute['name'] => $value];
                }
            }
            if (count($next) > self::MAX_COMBINATIONS) {
                return null;
            }
            $combinations = $next;
        }

        return $combinations;
    }

    private static function line(Product $product, array $choices, array $options, ?string $sku, int $price, int $stock): array
    {
        $chosen = array_intersect_key($options, array_flip($choices));

        $limits = array_filter([self::MAX_PER_LINE, max(0, $stock), $product->max_qty ?: null], fn ($n) => $n !== null);
        ksort($options);

        // The "was" price applies to the product's own price only; a variant priced on its
        // own has no list price to be discounted from.
        $listPrice = ! $product->sellsVariants() && (int) $product->compare_at_price > $price
            ? (int) $product->compare_at_price
            : $price;

        return [
            'key' => $product->id.'-'.substr(md5(json_encode($options)), 0, 8),
            'product_id' => $product->id,
            'name' => $product->name,
            'label' => $chosen ? implode(' / ', $chosen) : null,
            'options' => $options,
            'sku' => filled($sku) ? (string) $sku : null,
            'price' => $price,
            'price_formatted' => money($price),
            'list_price' => $listPrice,
            'discount_percent' => $listPrice > $price ? round(($listPrice - $price) * 100 / $listPrice, 1) : null,
            'stock' => $stock,
            'max' => (int) min($limits),
            'url' => $product->url,
            'unit' => count($product->sizes ?? []) === 1 && ! $chosen ? ($product->sizes[0] ?? null) : null,
            'image' => $product->image_url,
            'category' => $product->category?->name,
        ];
    }
}
