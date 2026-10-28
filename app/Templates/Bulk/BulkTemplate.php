<?php

namespace App\Templates\Bulk;

use App\Filament\Pages\BulkHomepageSettings;
use App\Filament\Pages\BulkSettings;
use App\Models\Category;
use App\Models\Setting;
use App\Support\Media;
use App\Templates\Template;
use Database\Seeders\BulkCatalogSeeder;
use Database\Seeders\BulkContentSeeder;
use Database\Seeders\BulkMenuSeeder;
use Database\Seeders\BulkSettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

/**
 * Bulk Order storefront: a store of its own, built around ordering many products in one
 * pass. The homepage leads with the bulk-order form (search, pick, set a quantity, repeat,
 * add everything to the cart); catalogue, product pages, cart and checkout are the same
 * as every other store. Its owner adds the products; the demo catalogue is a starting point.
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
        return 'Order-first store for buying in quantity — the homepage is a bulk-order form: search by name or code, pick a size, set quantities for many products and add them all to the cart at once. Own catalogue, standard cart and checkout, mobile-first.';
    }

    public function thumbnail(): ?string
    {
        return '/templates/bulk/thumbnail.jpg';
    }

    public function pwa(): array
    {
        return ['name' => $this->setting('site.logo_primary', 'Stock').$this->setting('site.logo_accent', 'Up'), 'theme_color' => '#0B3B36', 'background_color' => '#F6F8F7'];
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
            'header' => ['bulk_header', 'Header — Category navigation'],
            'footer_shop' => ['bulk_footer_shop', 'Footer — Shop'],
            'footer_support' => ['bulk_footer_support', 'Footer — Customer support'],
            'footer_company' => ['bulk_footer_company', 'Footer — Company'],
            'legal' => ['bulk_legal', 'Footer — Policies'],
        ];
    }

    public function settingGroups(): array
    {
        return ['site' => 'bulk_site', 'home' => 'bulk_home'];
    }

    public function adminPages(): array
    {
        return [BulkSettings::class, BulkHomepageSettings::class];
    }

    public function isInstalled(): bool
    {
        return Setting::get('bulk_home') !== null && Category::where('template', 'bulk')->exists();
    }

    public function install(): void
    {
        app(BulkSettingsSeeder::class)->run();
        app(BulkMenuSeeder::class)->run();
        app(BulkCatalogSeeder::class)->run();
        app(BulkContentSeeder::class)->run();
        Cache::flush();
    }

    public function boot(): void
    {
        View::composer('home.index', HomeComposer::class);
    }

    /** Category tree for the mega menu, mobile menu and footer. */
    public function viewData(): array
    {
        return [
            'navCategories' => Cache::remember('bulk.nav_categories', 600, fn () => Category::active()
                ->topLevel()->where('show_in_menu', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->get()),
        ];
    }

    public function defaults(): array
    {
        return [
            'site' => [
                'logo_primary' => 'Stock',
                'logo_accent' => 'Up',
                'tagline' => 'Wholesale supplies, ordered in one go',
                'usp_strip' => ['Order many products on one screen', 'Pack sizes for shops & offices', 'GST invoice on every order', 'Fast dispatch'],
                'search_placeholder' => 'Search products or codes…',
                'search_suggestions' => ['rice 25 kg', 'tea', 'sunflower oil', 'floor cleaner', 'biscuits carton', 'paper cups'],
                'support_phone' => '+91 98765 43210',
                'support_hours' => 'Mon–Sat, 9 am – 7 pm',
                'whatsapp_number' => '919876543210',
                'support_email' => 'orders@stockup.in',
                'delivery_promise' => 'Dispatch in 24 hours',
                'gst_note' => 'All prices inclusive of GST. GST invoice available on every order.',
                'bulk_heading' => 'Ordering more than the form allows?',
                'bulk_text' => 'For very large quantities, recurring supply or custom pack sizes, send us your list and we will reply with a quote.',
                'bulk_cta_label' => 'Request a quote',
                'bulk_cta_url' => '/contact?subject=Quote',
                'bulk_points' => ['Pricing for large quantities', 'Recurring monthly supply', 'Scheduled deliveries', 'GST invoice & credit terms'],
                'footer_blurb' => 'StockUp supplies pantry staples, beverages, snacks and cleaning essentials in pack sizes made for shops, offices, cafés and kitchens — ordered in one go.',
                'newsletter_heading' => 'Get price drops & new stock',
                'newsletter_text' => 'Wholesale offers, straight to your inbox. No spam.',
            ],
            'home' => [
                'sections' => [
                    ['key' => 'categories', 'enabled' => true],
                    ['key' => 'featured', 'enabled' => true],
                    ['key' => 'bulk', 'enabled' => true],
                    ['key' => 'deals', 'enabled' => false],
                    ['key' => 'bestsellers', 'enabled' => false],
                    ['key' => 'new', 'enabled' => false],
                    ['key' => 'brands', 'enabled' => false],
                    ['key' => 'why', 'enabled' => true],
                    ['key' => 'faq', 'enabled' => true],
                ],
                'hero_eyebrow' => 'Bulk ordering made simple',
                'hero_heading' => 'Order everything you need in one go',
                'hero_text' => 'No more adding products one at a time. Search, set quantities for every line and send the whole list to your cart in a single click.',
                'hero_primary_label' => 'Start your order',
                'hero_secondary_label' => 'Browse catalogue',
                'hero_secondary_url' => '/shop',
                'hero_steps' => [
                    ['title' => 'Search', 'text' => 'Find each product by name or code.'],
                    ['title' => 'Set quantities', 'text' => 'Choose the pack size and how many you need.'],
                    ['title' => 'Add all to cart', 'text' => 'One click — then check out as usual.'],
                ],
                'order_heading' => 'Your order',
                'order_text' => 'Click a search box to see popular products, or type a name or code.',
                'categories_heading' => 'Shop by category',
                'categories_text' => 'Prefer to browse? Every product can also be added from its page.',
                'categories_limit' => 9,
                'deals_heading' => 'Today’s deals',
                'deals_text' => 'Lower prices on selected stock.',
                'deals_limit' => 8,
                'featured_heading' => 'Most ordered',
                'featured_text' => 'What our customers reorder every week.',
                'featured_limit' => 10,
                'bestsellers_heading' => 'Bestsellers',
                'bestsellers_text' => 'Our most popular products.',
                'bestsellers_limit' => 10,
                'brands_heading' => 'Brands we stock',
                'brands_text' => 'Genuine stock from manufacturers and authorised distributors.',
                'new_heading' => 'New arrivals',
                'new_text' => 'Fresh additions to the catalogue.',
                'new_limit' => 10,
                'why_heading' => 'Why order from StockUp',
                'why_items' => [
                    ['icon' => 'bolt', 'title' => 'One-screen ordering', 'text' => 'Your whole list in one form, not fifty product pages.'],
                    ['icon' => 'box', 'title' => 'Wholesale pack sizes', 'text' => 'Bags, cartons and cases sized for businesses.'],
                    ['icon' => 'truck', 'title' => 'Fast dispatch', 'text' => 'Orders leave the warehouse within 24 hours.'],
                    ['icon' => 'receipt', 'title' => 'GST invoice', 'text' => 'Claim input credit on every order.'],
                    ['icon' => 'shield', 'title' => 'Secure payments', 'text' => 'UPI, cards, net banking and cash on delivery.'],
                    ['icon' => 'headset', 'title' => 'Order support', 'text' => 'Talk to our team on phone or WhatsApp.'],
                ],
                'faq_heading' => 'Frequently asked questions',
            ],
        ];
    }
}
