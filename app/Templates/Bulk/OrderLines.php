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
                    $lines[] = self::line($product, $choices, $variant['options'], $variant['sku'], $variant['price'], $variant['stock'], $variant['images'][0] ?? null, $variant['compare_at_price']);
                }
            }

            return $lines;
        }

        $combinations = self::combinations($attributes);
        if ($combinations === null) {
            return [];
        }

        foreach ($combinations as $options) {
            $lines[] = self::line($product, $choices, $options, $product->sku, (float) $product->price, (int) $product->stock, compareAt: $product->compareAtFor());
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

    private static function line(Product $product, array $choices, array $options, ?string $sku, float $price, int $stock, ?string $image = null, ?float $compareAt = null): array
    {
        $chosen = array_intersect_key($options, array_flip($choices));

        // No fixed cap per line: only the stock and the product's own "max quantity per order".
        $limits = array_filter([max(0, $stock), $product->max_qty ?: null], fn ($n) => $n !== null);
        ksort($options);

        // The "was" price: the variant's own compare-at price (or the product's, for a product
        // not sold by variant), only when it is above the price including tax (an MRP includes it).
        $basis = $product->mrpBasis($price);
        $listPrice = $compareAt !== null && $compareAt > $basis ? $compareAt : $price;

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
            'discount_percent' => $listPrice > $price ? round(($listPrice - $basis) * 100 / $listPrice, 1) : null,
            'stock' => $stock,
            'max' => (int) min($limits),
            // Said when a quantity is held back to `max` (the same words the cart uses).
            'limit_message' => self::limitMessage((int) min($limits), $product->max_qty ? (int) $product->max_qty : null),
            'url' => $product->url,
            'unit' => count($product->sizes ?? []) === 1 && ! $chosen ? ($product->sizes[0] ?? null) : null,
            // A combination with photos of its own shows its first one.
            'image' => $image ?? $product->image_url,
            'category' => $product->category?->name,
            'category_id' => $product->category_id,
        ];
    }

    /** Why a line cannot go higher: the product's max quantity per order, or its stock. */
    private static function limitMessage(int $max, ?int $maxPerOrder): string
    {
        if ($max <= 0) {
            return 'This item is out of stock.';
        }

        return $maxPerOrder !== null && $max === $maxPerOrder
            ? "You can order at most {$max} of this item."
            : "Only {$max} left in stock.";
    }
}
