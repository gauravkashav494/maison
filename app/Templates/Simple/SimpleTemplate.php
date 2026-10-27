<?php

namespace App\Templates\Simple;

use App\Filament\Pages\SiteSettings;
use App\Models\Category;
use App\Models\Setting;
use App\Templates\Template;
use Database\Seeders\SimpleCatalogSeeder;
use Database\Seeders\SimpleMenuSeeder;
use Database\Seeders\SimpleSettingsSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * A deliberately plain storefront: products as a list, no photography anywhere.
 *
 * It is for clients who sell from a price list — spares, consumables, stationery, hardware —
 * where a picture adds nothing and the customer wants name, code, price and stock on one
 * line. The table carries a quantity box and an add button per row, several rows can be added
 * at once, and search narrows the list in place.
 *
 * Everything behind it is the shared backend: the same cart, coupons, shipping, checkout,
 * orders and account pages as the other templates, so nothing new has to be maintained.
 */
class SimpleTemplate extends Template
{
    public function id(): string
    {
        return 'simple';
    }

    public function name(): string
    {
        return 'Simple List';
    }

    public function description(): string
    {
        return 'Image-free order list: every product as a row with its name, code, price and stock, a quantity box and an add button. Search narrows the list, several rows can be added at once, and the shared cart and checkout finish the order.';
    }

    public function pwa(): array
    {
        return [
            'name' => $this->setting('site.name', 'Order List'),
            'theme_color' => '#1f2937',
            'background_color' => '#ffffff',
        ];
    }

    public function viewPath(): ?string
    {
        return 'templates/simple';
    }

    public function assets(): array
    {
        return ['resources/templates/simple/css/app.css', 'resources/templates/simple/js/app.js'];
    }

    public function menuLocations(): array
    {
        return [
            'header' => ['simple_header', 'Header — Navigation'],
            'footer' => ['simple_footer', 'Footer — Links'],
            'legal' => ['simple_legal', 'Footer — Policies'],
        ];
    }

    public function settingGroups(): array
    {
        return ['site' => 'simple_site'];
    }

    public function adminPages(): array
    {
        return [SiteSettings::class];
    }

    public function isInstalled(): bool
    {
        return Setting::get('simple_site') !== null && Category::where('template', 'simple')->exists();
    }

    public function install(): void
    {
        app(SimpleSettingsSeeder::class)->run();
        app(SimpleMenuSeeder::class)->run();
        app(SimpleCatalogSeeder::class)->run();
        Cache::flush();
    }

    public function defaults(): array
    {
        return [
            'site' => [
                'name' => 'Order List',
                'tagline' => 'Everything we stock, on one page',
                'intro' => 'Search or scroll the list, set the quantity you need and add it to your order. Prices include GST.',
                'search_placeholder' => 'Search by name or product code…',
                'empty_text' => 'Nothing matches that search. Try a shorter word or a product code.',
                'support_email' => 'orders@example.com',
                'support_phone' => '+91 98765 43210',
                'footer_blurb' => 'Order by list, delivered from stock. Prices include GST; a GST invoice comes with every order.',
                'show_stock' => true,
                'per_page' => 50,
            ],
        ];
    }
}
