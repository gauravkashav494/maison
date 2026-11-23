<?php

namespace App\Support;

/**
 * A product's (or variant's) price including and excluding tax, worked out from its saved
 * `price` and tax rate. `price` is the one the store's prices are entered as (Site settings →
 * Payments & tax): including tax when $pricesIncludeTax, else before tax.
 */
class TaxPrices
{
    /** A price including tax from one before it, or the reverse, rounded to the paisa. */
    public static function convert(float $price, float $rate, bool $toInclusive): float
    {
        return round($toInclusive ? $price * (1 + $rate / 100) : $price / (1 + $rate / 100), 2);
    }

    /**
     * Both prices for a saved `price`; both null when there is none (a variant using the
     * product's price).
     *
     * @return array{price_incl_tax: ?float, price_excl_tax: ?float}
     */
    public static function for(mixed $price, float $rate, bool $pricesIncludeTax): array
    {
        if (blank($price) || ! is_numeric($price)) {
            return ['price_incl_tax' => null, 'price_excl_tax' => null];
        }

        $saved = round((float) $price, 2);
        $other = self::convert($saved, $rate, toInclusive: ! $pricesIncludeTax);

        return $pricesIncludeTax
            ? ['price_incl_tax' => $saved, 'price_excl_tax' => $other]
            : ['price_incl_tax' => $other, 'price_excl_tax' => $saved];
    }

    /**
     * $values with both prices brought in line with its `price` and rate, when they are missing or
     * no longer match (the price or rate changed elsewhere, e.g. an import). Prices that already
     * match are kept as they are, so a price typed on the form is never moved by a paisa.
     *
     * @param  array<string, mixed>  $values  holds price, price_incl_tax, price_excl_tax
     * @return array<string, mixed>
     */
    public static function sync(array $values, float $rate, bool $pricesIncludeTax): array
    {
        $expected = self::for($values['price'] ?? null, $rate, $pricesIncludeTax);
        if ($expected['price_incl_tax'] === null) {
            return array_merge($values, $expected);
        }

        $matches = fn (string $key) => is_numeric($values[$key] ?? null) && abs((float) $values[$key] - $expected[$key]) <= 0.011;
        if ($matches('price_incl_tax') && $matches('price_excl_tax')) {
            return $values;
        }

        return array_merge($values, $expected);
    }
}
