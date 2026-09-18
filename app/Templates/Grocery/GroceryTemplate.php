<?php

namespace App\Templates\Grocery;

use App\Filament\Pages\GroceryHomepageSettings;
use App\Filament\Pages\GrocerySettings;
use App\Models\Category;
use App\Models\Setting;
use App\Support\Media;
use App\Templates\Template;
use Database\Seeders\GroceryCatalogSeeder;
use Database\Seeders\GroceryMenuSeeder;
use Database\Seeders\GrocerySettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

/**
 * Indian grocery storefront: quick-commerce style header with delivery promise and
 * category bar, offer banners, deals, unit/pack-based product cards with quantity
 * steppers, veg/non-veg marks and a fast checkout.
 */
class GroceryTemplate extends Template
{
    public function id(): string
    {
        return 'grocery';
    }

    public function name(): string
    {
        return 'Indian Grocery';
    }

    public function description(): string
    {
        return 'Fast, friendly grocery storefront — delivery-first header with search and category bar, offer banners, deals of the day, pack-size product cards with quantity steppers, veg/non-veg marks and a one-page checkout.';
    }

    public function thumbnail(): ?string
    {
        return '/templates/grocery/thumbnail.jpg';
    }

    public function viewPath(): ?string
    {
        return 'templates/grocery';
    }

    public function assets(): array
    {
        return ['resources/templates/grocery/css/app.css', 'resources/templates/grocery/js/app.js'];
    }

    public function menuLocations(): array
    {
        return [
            'header' => ['grocery_header', 'Header quick links'],
            'footer_categories' => ['grocery_footer_categories', 'Footer — Categories'],
            'footer_help' => ['grocery_footer_help', 'Footer — Help'],
            'footer_company' => ['grocery_footer_company', 'Footer — Company'],
            'legal' => ['grocery_legal', 'Footer — Legal links'],
        ];
    }

    public function settingGroups(): array
    {
        return ['site' => 'grocery_site', 'home' => 'grocery_home'];
    }

    public function adminPages(): array
    {
        return [GrocerySettings::class, GroceryHomepageSettings::class];
    }

    public function isInstalled(): bool
    {
        return Setting::get('grocery_home') !== null && Category::where('template', 'grocery')->exists();
    }

    public function install(): void
    {
        app(GrocerySettingsSeeder::class)->run();
        app(GroceryMenuSeeder::class)->run();
        app(GroceryCatalogSeeder::class)->run();
        Cache::flush();
    }

    public function boot(): void
    {
        View::composer('home.index', HomeComposer::class);
    }

    /** Data shared with the grocery layout and partials (category navigation). */
    public function viewData(): array
    {
        return [
            'navCategories' => Cache::remember('grocery.nav_categories', 600, fn () => Category::active()
                ->topLevel()->where('show_in_menu', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->get()),
        ];
    }

    public function defaults(): array
    {
        return [
            'site' => [
                'logo_primary' => 'Maison',
                'logo_accent' => 'Fresh',
                'tagline' => 'Groceries delivered in minutes',
                'delivery_promise' => 'Delivery in 10–30 min',
                'delivery_area' => 'Bengaluru',
                'offer_strip_text' => 'Flat 10% off your first order with code WELCOME10',
                'offer_strip_link_label' => 'See all offers',
                'offer_strip_link_url' => '/shop/sale',
                'search_placeholder' => 'Search for atta, dal, milk, snacks…',
                'search_suggestions' => ['Atta', 'Toor dal', 'Milk', 'Basmati rice', 'Ghee', 'Chips'],
                'support_phone' => null,
                'support_hours' => '7 am – 11 pm, all days',
                'whatsapp_number' => null,
                'footer_blurb' => 'Fresh produce, daily essentials and your favourite brands — delivered to your door from our neighbourhood dark stores.',
                'app_heading' => 'Get the app',
                'app_text' => 'Order faster, track live and get app-only deals.',
                'app_store_url' => '#',
                'play_store_url' => '#',
                'veg_filter_label' => 'Veg only',
                'show_veg_filter' => true,
            ],
            'home' => [
                'hero_banners' => [
                    [
                        'eyebrow' => 'Fresh every morning',
                        'heading' => 'Farm-fresh fruits & vegetables',
                        'text' => 'Picked at dawn, at your door by breakfast. Up to 30% off this week.',
                        'cta_label' => 'Shop fresh',
                        'cta_url' => '/shop/fruits-vegetables',
                        'image' => Media::unsplash('1540420773420-3366772f4999', 1400, '&h=700'),
                        'theme' => 'green',
                    ],
                    [
                        'eyebrow' => 'Monthly essentials',
                        'heading' => 'Atta, rice, dal & oil — stocked up',
                        'text' => 'Big packs, bigger savings. Free delivery on orders over ₹499.',
                        'cta_label' => 'Shop staples',
                        'cta_url' => '/shop/atta-rice-dal',
                        'image' => Media::unsplash('1586201375761-83865001e31c', 1400, '&h=700'),
                        'theme' => 'saffron',
                    ],
                    [
                        'eyebrow' => 'Snack o’clock',
                        'heading' => 'Chips, namkeen & biscuits',
                        'text' => 'Buy 2 get 1 free on selected packs from Haldiram’s, Lay’s and Britannia.',
                        'cta_label' => 'Grab a snack',
                        'cta_url' => '/shop/snacks-namkeen',
                        'image' => Media::unsplash('1566478989037-eec170784d0b', 1400, '&h=700'),
                        'theme' => 'berry',
                    ],
                ],
                'promo_tiles' => [
                    ['title' => 'Under ₹99', 'text' => 'Everyday steals', 'cta_label' => 'Shop now', 'url' => '/shop?max=99', 'image' => Media::unsplash('1608686207856-001b95cf60ca', 600, '&h=600'), 'color' => '#fff4d6'],
                    ['title' => 'Dairy & breakfast', 'text' => 'Milk, bread, eggs — daily', 'cta_label' => 'Shop now', 'url' => '/shop/dairy-bread-eggs', 'image' => Media::unsplash('1550583724-b2692b85b150', 600, '&h=600'), 'color' => '#e3f2ff'],
                    ['title' => 'Deals of the day', 'text' => 'Up to 40% off', 'cta_label' => 'See deals', 'url' => '/shop/sale', 'image' => Media::unsplash('1596040033229-a9821ebd058d', 600, '&h=600'), 'color' => '#ffe6e6'],
                ],
                'sections' => [
                    ['key' => 'categories', 'enabled' => true],
                    ['key' => 'promos', 'enabled' => true],
                    ['key' => 'deals', 'enabled' => true],
                    ['key' => 'fresh', 'enabled' => true],
                    ['key' => 'banner', 'enabled' => true],
                    ['key' => 'bestsellers', 'enabled' => true],
                    ['key' => 'new', 'enabled' => true],
                    ['key' => 'brands', 'enabled' => true],
                    ['key' => 'promises', 'enabled' => true],
                    ['key' => 'app', 'enabled' => true],
                ],
                'categories_heading' => 'Shop by category',
                'categories_limit' => 12,
                'deals_heading' => 'Deals of the day',
                'deals_text' => 'Prices drop every morning — grab them before they’re gone.',
                'deals_limit' => 10,
                'fresh_heading' => 'Fresh from the farm',
                'fresh_text' => 'Fruits and vegetables sourced daily from local farmers.',
                'fresh_category_slug' => 'fruits-vegetables',
                'fresh_limit' => 10,
                'banner_heading' => 'Ghee, oils & masalas for the everyday kitchen',
                'banner_text' => 'Trusted brands — Fortune, Amul, MDH, Everest — at prices that beat the kirana.',
                'banner_cta_label' => 'Stock the pantry',
                'banner_cta_url' => '/shop/oils-ghee-masalas',
                'banner_image' => Media::unsplash('1596797038530-2c107229654b', 1600, '&h=700'),
                'bestsellers_heading' => 'Bestsellers',
                'bestsellers_text' => 'What your neighbours are buying this week.',
                'bestsellers_limit' => 10,
                'new_heading' => 'New in store',
                'new_text' => 'Just added to the shelves.',
                'new_limit' => 10,
                'brands_heading' => 'Top brands',
                'promises' => [
                    ['icon' => 'bolt', 'title' => 'Superfast delivery', 'text' => 'From our dark store to your door in 10–30 minutes.'],
                    ['icon' => 'leaf', 'title' => 'Fresh guarantee', 'text' => 'Not fresh? Get a replacement or refund, no questions asked.'],
                    ['icon' => 'tag', 'title' => 'Best prices', 'text' => 'Everyday low prices and daily deals across 5,000+ products.'],
                    ['icon' => 'shield', 'title' => 'Safe & secure', 'text' => 'UPI, cards, wallets and cash on delivery — all protected.'],
                ],
                'app_heading' => 'Groceries in your pocket',
                'app_text' => 'Download the app for live order tracking, one-tap reorders and app-only offers.',
                'app_image' => Media::unsplash('1601648764658-cf37e8c89b70', 900, '&h=1100'),
                'newsletter_heading' => 'Get ₹100 off your next order',
                'newsletter_text' => 'Sign up for weekly deals and new arrivals. No spam, only savings.',
            ],
        ];
    }
}
