<?php

namespace App\Support;

use App\Clients\ClientContext;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Suggests the next product code (SKU) for the client in context, following the pattern its
 * products already use: with SU-1001 … SU-1012 in the catalogue the next one is SU-1013. A
 * client without coded products starts at <initials>-1001 (Trade Supplies → TS-1001).
 */
class SkuGenerator
{
    private const FIRST = 1001;

    public static function next(): string
    {
        // Trashed products still hold their code (the unique index covers them).
        $skus = Product::withTrashed()->whereNotNull('sku')->pluck('sku')->map(fn ($sku) => (string) $sku);

        $numbered = $skus
            ->map(fn (string $sku) => preg_match('/^(.*?)(\d+)$/', $sku, $m) ? ['prefix' => $m[1], 'number' => $m[2]] : null)
            ->filter();

        if ($numbered->isEmpty()) {
            return self::free(self::initials().'-', self::FIRST, 4, $skus->all());
        }

        // The prefix most products share, then the highest number under it.
        $prefix = $numbered->countBy('prefix')->sortDesc()->keys()->first();
        $numbers = $numbered->where('prefix', $prefix)->pluck('number');
        $width = strlen((string) $numbers->sortByDesc(fn ($n) => (int) $n)->first());

        return self::free($prefix, $numbers->map(fn ($n) => (int) $n)->max() + 1, $width, $skus->all());
    }

    /** The first code from $number up that no product uses yet. */
    private static function free(string $prefix, int $number, int $width, array $taken): string
    {
        $taken = array_flip(array_map('mb_strtolower', $taken));
        do {
            $sku = $prefix.str_pad((string) $number++, $width, '0', STR_PAD_LEFT);
        } while (isset($taken[mb_strtolower($sku)]));

        return $sku;
    }

    private static function initials(): string
    {
        $name = (string) (app(ClientContext::class)->client()?->name ?: setting('site.name', ''));
        $initials = Str::of($name)->ascii()->upper()->explode(' ')
            ->map(fn (string $word) => preg_replace('/[^A-Z0-9]/', '', $word))
            ->filter()
            ->map(fn (string $word) => $word[0])
            ->take(3)
            ->implode('');

        return $initials !== '' ? $initials : 'SKU';
    }
}
