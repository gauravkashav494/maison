<?php

use App\Models\Setting;

if (! function_exists('money')) {
    /** Format rupee amounts as ₹12,900.50 (Indian digit grouping, always two decimals). */
    function money(int|float|string|null $amount): string
    {
        $amount = round((float) ($amount ?? 0), 2);
        [$s, $paise] = explode('.', number_format(abs($amount), 2, '.', ''));
        if (strlen($s) > 3) {
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $s = $rest.','.$last3;
        }

        return ($amount < 0 ? '-' : '').'₹'.$s.'.'.$paise;
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('prices_include_tax')) {
    /**
     * Whether the store's prices already include tax (retail) or tax is added on top at
     * checkout (B2B, the default). Set under Site settings → Payments & tax.
     */
    function prices_include_tax(): bool
    {
        return setting('checkout.tax_mode', 'exclusive') === 'inclusive';
    }
}

if (! function_exists('tax_line')) {
    /** Label for the tax row of a cart or order, e.g. "GST (18%)" or "Includes GST (18%)". */
    function tax_line(?string $label, float|int|string|null $rate, bool $inclusive): string
    {
        $label = filled($label) ? $label : 'Tax';
        $rate = (float) $rate;
        $percent = $rate > 0 ? ' ('.rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.').'%)' : '';

        return ($inclusive ? 'Includes ' : '').$label.$percent;
    }
}

if (! function_exists('price_tax_note')) {
    /**
     * Short note for under a price: "Inclusive of all taxes", "+ 18% GST, added at checkout",
     * or '' when prices are before tax and no tax is charged.
     */
    function price_tax_note(): string
    {
        $rate = (float) setting('checkout.tax_rate', 0);
        if (prices_include_tax()) {
            return 'Inclusive of all taxes';
        }
        if ($rate <= 0) {
            return '';
        }

        return '+ '.rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.').'% '.setting('checkout.tax_label', 'GST').', added at checkout';
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

if (! function_exists('store_phone')) {
    /** The store's support phone: the template's override (Appearance) or Site settings → Contact phone. */
    function store_phone(): ?string
    {
        return tsetting('site.support_phone') ?: setting('site.contact_phone');
    }
}

if (! function_exists('store_email')) {
    /** The store's support email: the template's override (Appearance) or Site settings → Client care email. */
    function store_email(): ?string
    {
        return tsetting('site.support_email') ?: setting('site.contact_email');
    }
}
