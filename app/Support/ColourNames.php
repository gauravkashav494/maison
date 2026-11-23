<?php

namespace App\Support;

/**
 * Swatch colours for common colour names, so a Colour option with values like "Red" or
 * "Navy blue" shows round swatches without anyone picking a colour by hand. A colour picked
 * in the product form always wins; a name not listed here simply shows as text.
 */
class ColourNames
{
    private const HEX = [
        'black' => '#111111', 'white' => '#ffffff', 'off white' => '#f5f1e8', 'ivory' => '#fffff0', 'cream' => '#f3ead3',
        'grey' => '#8a8f98', 'gray' => '#8a8f98', 'light grey' => '#c9cdd3', 'light gray' => '#c9cdd3', 'dark grey' => '#4b5058', 'dark gray' => '#4b5058',
        'charcoal' => '#36454f', 'silver' => '#c0c0c0', 'gold' => '#d4af37', 'golden' => '#d4af37', 'rose gold' => '#d7a07c', 'copper' => '#b87333', 'bronze' => '#a97142',
        'red' => '#d32f2f', 'dark red' => '#8b1a1a', 'maroon' => '#800000', 'wine' => '#722f37', 'burgundy' => '#800020', 'crimson' => '#dc143c', 'rust' => '#b7410e',
        'pink' => '#f48fb1', 'light pink' => '#f8c8d8', 'hot pink' => '#ff69b4', 'rose' => '#e8768a', 'magenta' => '#c2185b', 'fuchsia' => '#d81b85', 'peach' => '#ffcba4', 'coral' => '#ff7f50',
        'orange' => '#f57c00', 'saffron' => '#f4a300', 'mustard' => '#d4a017', 'yellow' => '#fbc02d', 'lemon' => '#fff44f', 'amber' => '#ffbf00',
        'green' => '#2e7d32', 'dark green' => '#1b4d2a', 'bottle green' => '#006a4e', 'olive' => '#708238', 'mint' => '#98ff98', 'sea green' => '#2e8b57', 'lime' => '#9acd32', 'emerald' => '#50c878',
        'blue' => '#1e63c6', 'light blue' => '#90caf9', 'sky blue' => '#87ceeb', 'royal blue' => '#4169e1', 'navy' => '#1f2a44', 'navy blue' => '#1f2a44', 'dark blue' => '#0d2b5c',
        'teal' => '#008080', 'turquoise' => '#40e0d0', 'aqua' => '#00bcd4', 'cyan' => '#00bcd4', 'indigo' => '#3f51b5',
        'purple' => '#7b1fa2', 'violet' => '#8f00ff', 'lavender' => '#b57edc', 'lilac' => '#c8a2c8', 'plum' => '#8e4585', 'mauve' => '#b784a7',
        'brown' => '#6d4c41', 'dark brown' => '#4e342e', 'chocolate' => '#5d3a1a', 'coffee' => '#6f4e37', 'tan' => '#d2b48c', 'beige' => '#e8d9b5', 'khaki' => '#c3b091', 'camel' => '#c19a6b', 'nude' => '#e3bc9a', 'sand' => '#d8c8a8',
    ];

    /**
     * The swatch colour for a colour name ("Navy Blue", "off-white"), or for a value that is a
     * colour code itself ("#cc6363", "cc6363", "#c66"), or null when unknown.
     */
    public static function hex(?string $name): ?string
    {
        // The short form needs its "#", so a plain number such as "123" is not read as a colour.
        if (preg_match('/^(?:#([0-9a-f]{3}|[0-9a-f]{6})|([0-9a-f]{6}))$/i', trim((string) $name), $m)) {
            $code = strtolower($m[1] !== '' ? $m[1] : $m[2]);

            return '#'.(strlen($code) === 3 ? $code[0].$code[0].$code[1].$code[1].$code[2].$code[2] : $code);
        }

        $key = trim(preg_replace('/[\s_-]+/', ' ', mb_strtolower((string) $name)));

        return self::HEX[$key] ?? null;
    }
}
