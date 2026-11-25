<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Saved items (the wishlist). An entry is what the shopper saved:
 *  - a product id: the product, no size chosen (from a card, or older lists);
 *  - {id, options}: exactly that size / colour, as it would go in the cart.
 *
 * The list lives in the browser (resources/js/saved-items.js) and, for a signed-in buyer of a
 * store that keeps it, on the account (customers.wishlist, in the same form). Read here for
 * the Saved items page: every entry is checked against the product as it is now, so a size
 * that was taken off sale stays on the list marked unavailable instead of quietly vanishing.
 */
class SavedItems
{
    /** At most this many entries are kept and shown. */
    public const MAX = 100;

    /**
     * Entries from a request: a JSON list (?items=…), a list, or the older "3,7,9" of ids.
     *
     * @return list<array{id:int, options:array<string,string>}>
     */
    public static function parse(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }

        $entries = [];
        foreach (is_array($raw) ? $raw : [] as $item) {
            $id = (int) (is_array($item) ? ($item['id'] ?? 0) : (is_numeric($item) ? $item : 0));
            if ($id <= 0) {
                continue;
            }
            $options = [];
            foreach (is_array($item) && is_array($item['options'] ?? null) ? $item['options'] : [] as $name => $value) {
                if (is_scalar($value) && trim((string) $name) !== '' && trim((string) $value) !== '') {
                    $options[mb_substr(trim((string) $name), 0, 60)] = mb_substr(trim((string) $value), 0, 60);
                }
            }
            $entry = ['id' => $id, 'options' => $options];
            if (! in_array(self::key($entry), array_map(self::key(...), $entries), true)) {
                $entries[] = $entry;
            }
            if (count($entries) >= self::MAX) {
                break;
            }
        }

        return $entries;
    }

    /**
     * As kept on the account and in the browser: an id, or {id, options}.
     *
     * @param  list<array{id:int, options:array<string,string>}>  $entries
     * @return list<int|array{id:int, options:array<string,string>}>
     */
    public static function forStorage(array $entries): array
    {
        return array_map(fn (array $e) => $e['options'] ? ['id' => $e['id'], 'options' => $e['options']] : $e['id'], $entries);
    }

    /** One string per entry, the same however the option names are spelt or ordered. */
    public static function key(array $entry): string
    {
        $options = [];
        foreach ($entry['options'] as $name => $value) {
            $canon = Product::isColourAttribute((string) $name) ? 'colour' : (Product::isSizeAttribute((string) $name) ? 'size' : mb_strtolower((string) $name));
            $options[$canon] = mb_strtolower((string) $value);
        }
        ksort($options);

        return $entry['id'].($options ? ':'.http_build_query($options) : '');
    }

    /**
     * The entries checked against their products, in the order saved. An entry whose product is
     * gone or hidden is left out. Each comes with:
     *  - options: the full choice to put in the cart (options with a single value filled in), or
     *    [] for a product saved without a size;
     *  - label: "Size: M" (null without a size); available: false when that size is no longer sold.
     *
     * @param  list<array{id:int, options:array<string,string>}>  $entries
     * @return Collection<int, array{key:string, product:Product, saved:array<string,string>, options:array<string,string>, label:?string, available:bool}>
     */
    public static function resolve(array $entries): Collection
    {
        $ids = array_values(array_unique(array_column($entries, 'id')));
        $products = $ids ? Product::with('category')->active()->whereIn('id', $ids)->get()->keyBy('id') : collect();

        return collect($entries)
            ->filter(fn (array $e) => $products->has($e['id']))
            ->map(fn (array $e) => self::check($products[$e['id']], $e))
            ->values();
    }

    private static function check(Product $product, array $entry): array
    {
        $base = ['key' => self::key($entry), 'product' => $product, 'saved' => $entry['options']];
        if (! $entry['options']) {
            return $base + ['options' => [], 'label' => null, 'available' => true];
        }

        $attributes = $product->variantAttributes();
        $chosen = $product->normaliseOptions($entry['options']);
        // Options with one value are not a choice, so they are not saved; the cart needs them.
        $full = [];
        foreach ($attributes as $attribute) {
            $value = $chosen[$attribute['name']] ?? (count($attribute['values']) === 1 ? $attribute['values'][0] : null);
            if ($value !== null) {
                $full[$attribute['name']] = $value;
            }
        }

        $available = count($chosen) === count($entry['options'])
            && count($full) === count($attributes)
            && (! $product->sellsVariants() || $product->findVariant($full) !== null);
        $shown = $available ? array_intersect_key($full, $chosen) : $entry['options'];

        return $base + [
            'options' => $available ? $full : [],
            'label' => implode(' · ', array_map(fn ($name, $value) => "{$name}: {$value}", array_keys($shown), $shown)),
            'available' => $available,
        ];
    }

    /**
     * A product card (Product::toCard()) for one saved entry: an exact size shows that size's
     * price, stock and photo, and offers only that size, so the card adds exactly what was saved.
     * `saved` tells the card which heart it is and what to say.
     */
    public static function card(array $item): array
    {
        /** @var Product $product */
        $product = $item['product'];
        $card = $product->toCard();
        $card['saved'] = [
            'key' => $item['key'],
            'chosen' => $item['saved'] ?: null,
            'options' => $item['options'] ?: null,
            'label' => $item['label'],
            'available' => $item['available'],
        ];

        if (! $item['saved']) {
            return $card;
        }
        if (! $item['available']) {
            return ['in_stock' => false, 'sizes' => [], 'unit' => null] + $card;
        }

        $options = $item['options'];
        $variant = $product->sellsVariants() ? $product->findVariant($options) : null;
        $price = $product->priceFor($options);
        $pricing = Product::pricing($price, $product->compareAtFor($options), $product->mrpBasis($price));
        $size = collect($options)->first(fn ($value, $name) => Product::isSizeAttribute((string) $name));
        $photo = $variant['images'][0] ?? collect($product->optionImages())
            ->first(fn (array $set) => array_intersect_assoc($set['options'], $options) === $set['options'])['images'][0] ?? null;

        return [
            ...$card,
            'price' => $pricing['price'],
            'price_formatted' => $pricing['price_formatted'],
            'compare_at_price' => $pricing['compare_at_price'],
            'compare_at_price_formatted' => $pricing['compare_at_price_formatted'],
            'discount_percent' => $pricing['discount_percent'],
            'in_stock' => $product->stockFor($options) > 0,
            'images' => $photo ? [$photo, ...array_values(array_diff($card['images'], [$photo]))] : $card['images'],
            // One size: the card's own logic then adds exactly this one.
            'sizes' => $size !== null ? [$size] : [],
            'unit' => $size ?? $card['unit'],
        ];
    }
}
