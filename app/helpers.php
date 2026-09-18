<?php

use App\Models\Setting;

if (! function_exists('money')) {
    /** Format whole-rupee amounts as ₹12,900 (Indian digit grouping). */
    function money(int|float|null $amount): string
    {
        $amount = (int) round($amount ?? 0);
        $s = (string) abs($amount);
        if (strlen($s) > 3) {
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $s = $rest.','.$last3;
        }

        return ($amount < 0 ? '-' : '').'₹'.$s;
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('emph')) {
    /**
     * Escape text, then turn *word* into <em>word</em> and newlines into <br>.
     * Used for editorial headings edited in the admin.
     */
    function emph(?string $text): \Illuminate\Support\HtmlString
    {
        $safe = e($text ?? '');
        $safe = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $safe);
        $safe = nl2br($safe, false);

        return new \Illuminate\Support\HtmlString($safe);
    }
}

if (! function_exists('template')) {
    /** The storefront template rendering the current request. */
    function template(): \App\Templates\Template
    {
        return app(\App\Templates\TemplateManager::class)->current();
    }
}

if (! function_exists('tsetting')) {
    /** Template-scoped setting with config defaults, e.g. tsetting('home.hero_banners'). */
    function tsetting(string $key, mixed $default = null): mixed
    {
        return template()->setting($key, $default);
    }
}
