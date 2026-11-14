<?php

namespace App\Templates\Bulk;

use App\Clients\ClientDatabase;
use App\Filament\Pages\BulkOrderSettings;
use App\Models\Category;
use App\Models\Setting;
use App\Support\Media;
use App\Templates\Template;
use Database\Seeders\BulkCatalogSeeder;
use Database\Seeders\BulkMenuSeeder;
use Database\Seeders\BulkPagesSeeder;
use Database\Seeders\BulkSettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

/**
 * A storefront for buyers who order many lines at once: trade counters, restaurants, offices,
 * resellers restocking from a wholesaler.
 *
 * This template is an order form laid out like an invoice: the buyer adds rows, searching each item by name or code — every
 * variant is its own item with its own code, price and stock — sets a quantity, sees rate,
 * discount and amount per row, and adds the lot in one go. A second tab takes a pasted list of
 * codes and quantities (or a CSV exported from their own system) and checks every code before
 * anything is added; a signed-in buyer can load a past order back into the form to repeat it.
 *
 * Prices, cart, coupons, shipping, checkout, orders and accounts are the shared backend; this
 * template changes only how an order is put together.
 */
class BulkTemplate extends Template
{
    public function id(): string
    {
        return 'bulk';
    }

    public function name(): string
    {
        return 'Bulk Order';
    }

    public function description(): string
    {
        return 'Order form for trade and repeat buyers: an invoice-style form where each row is an item searched by name or code (every variant is its own item), with quantity, rate, discount and amount, all added in one go; a quick-order pad for pasted codes or a CSV; and one-click reloading of a past order.';
    }

    public function pwa(): array
    {
        return [
            'name' => setting('site.name', 'Bulk Order'),
            'theme_color' => $this->setting('site.brand_color', '#0f4c45'),
            'background_color' => '#f6f7f6',
        ];
    }

    public function productFields(): array
    {
        return [self::PRODUCT_FIELDS_ORDER_LIMIT];
    }

    public function viewPath(): ?string
    {
        return 'templates/bulk';
    }

    public function assets(): array
    {
        return ['resources/templates/bulk/css/app.css', 'resources/templates/bulk/js/app.js'];
    }

    public function menuLocations(): array
    {
        return [
            'header' => ['bulk_header', 'Header — Links beside the categories'],
            'footer' => ['bulk_footer', 'Footer — Help'],
            'legal' => ['bulk_legal', 'Footer — Policies'],
        ];
    }

    public function thumbnail(): ?string
    {
        return '/templates/bulk/thumbnail.jpg';
    }

    public function settingGroups(): array
    {
        return ['site' => 'bulk_site'];
    }

    public function adminPages(): array
    {
        return [BulkOrderSettings::class];
    }

    public function isInstalled(): bool
    {
        return Setting::get('bulk_site') !== null && Category::where('template', 'bulk')->exists();
    }

    public function install(): void
    {
        app(BulkSettingsSeeder::class)->run();
        app(BulkMenuSeeder::class)->run();
        app(BulkCatalogSeeder::class)->run();
        app(BulkPagesSeeder::class)->run();
        Cache::flush();
    }

    public function boot(): void
    {
        // The Bulk order section lives on the home page.
        View::composer('home.index', HomeComposer::class);
    }

    /** Category navigation for the header, on every page. */
    public function viewData(): array
    {
        return [
            'navCategories' => Cache::remember(app(ClientDatabase::class)->cacheKey('bulk.nav_categories'), 600, fn () => Category::active()
                ->topLevel()->where('show_in_menu', true)->get()),
        ];
    }

    public function defaults(): array
    {
        return [
            'site' => [
                // Brand
                'brand_color' => '#0f4c45',
                'announcement' => '',
                // Home banner
                'hero_eyebrow' => 'Trade supplies at wholesale prices',
                'hero_heading' => 'Everything your kitchen, cleaning team and office runs on — in one order',
                'hero_text' => 'Browse the catalogue, or add your whole list at once with Bulk order. Codes, stock and prices are checked as you go.',
                'hero_cta_label' => 'Start a bulk order',
                'hero_secondary_label' => 'Browse the catalogue',
                // Bulk order section
                'heading' => 'Bulk order',
                'intro' => 'Add one row per item — search by name or code, set the quantity, and add the lot to your order in one go.',
                'pad_heading' => 'Paste a list',
                'pad_help' => 'One line per product: the code, then the quantity. Paste straight from a spreadsheet, or upload a CSV.',
                'pad_example' => "CL-FC-5L, 4\nPK-CT-500, 10\nGL-NT-M 6",
                'search_placeholder' => 'Search by name or code…',
                'empty_text' => 'No item matches that. Try a shorter word or a product code.',
                // Support & footer
                'support_email' => null,
                'support_phone' => null,
                'support_hours' => 'Mon–Sat, 9 am – 6 pm',
                'footer_blurb' => 'Wholesale supplies for kitchens, cleaning teams and offices.',
                'newsletter_heading' => 'Get trade price updates',
                'newsletter_text' => 'New stock and price-list changes, straight to your inbox. No spam.',
                'show_stock' => true,
                // Customer sign-in pages: hands receiving a parcel, behind the brand panel.
                'login_image' => Media::unsplash('1566576721346-d4a3b4eaeb55', 1000, '&h=1400'),
            ],
        ];
    }

    public function teamLogin(): array
    {
        return array_replace(parent::teamLogin(), [
            // Warehouse racking stacked with cartons: where the team running this store works.
            'image' => Media::unsplash('1587293852726-70cdb56c2866', 1600, '&h=1200'),
            'kicker' => 'Trade desk',
            'quote' => 'Big orders, kept simple.',
            'quote_sub' => 'Codes, stock and repeat orders — managed from one place.',
        ]);
    }
}
