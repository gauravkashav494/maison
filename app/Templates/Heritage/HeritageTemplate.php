<?php

namespace App\Templates\Heritage;

use App\Filament\Pages\HeritageHomepageSettings;
use App\Filament\Pages\HeritageSettings;
use App\Models\Category;
use App\Models\Setting;
use App\Support\Media;
use App\Templates\Template;
use Database\Seeders\HeritageCatalogSeeder;
use Database\Seeders\HeritageContentSeeder;
use Database\Seeders\HeritageMenuSeeder;
use Database\Seeders\HeritageSettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

/**
 * Premium Indian grocery storefront: red / gold / cream identity, editorial serif
 * headings, deep mega-menu, rich category pages, nutrition-aware product pages.
 */
class HeritageTemplate extends Template
{
    public function id(): string
    {
        return 'heritage';
    }

    public function name(): string
    {
        return 'Heritage Grocery';
    }

    public function description(): string
    {
        return 'Premium Indian grocery & organic foods — deep red, warm gold and cream, editorial typography, multi-level category navigation, rich category pages, nutrition tabs, quick view, save-for-later and a testimonial-led homepage.';
    }

    public function thumbnail(): ?string
    {
        return '/templates/heritage/thumbnail.jpg';
    }

    public function viewPath(): ?string
    {
        return 'templates/heritage';
    }

    public function assets(): array
    {
        return ['resources/templates/heritage/css/app.css', 'resources/templates/heritage/js/app.js'];
    }

    public function menuLocations(): array
    {
        return [
            'header' => ['heritage_header', 'Header links (after the category menu)'],
            'footer_shop' => ['heritage_footer_shop', 'Footer — Shop'],
            'footer_help' => ['heritage_footer_help', 'Footer — Customer support'],
            'footer_company' => ['heritage_footer_company', 'Footer — About'],
            'legal' => ['heritage_legal', 'Footer — Legal links'],
        ];
    }

    public function settingGroups(): array
    {
        return ['site' => 'heritage_site', 'home' => 'heritage_home'];
    }

    public function adminPages(): array
    {
        return [HeritageSettings::class, HeritageHomepageSettings::class];
    }

    public function isInstalled(): bool
    {
        return Setting::get('heritage_home') !== null && Category::where('template', 'heritage')->exists();
    }

    public function install(): void
    {
        app(HeritageSettingsSeeder::class)->run();
        app(HeritageMenuSeeder::class)->run();
        app(HeritageCatalogSeeder::class)->run();
        app(HeritageContentSeeder::class)->run();
        Cache::flush();
    }

    public function boot(): void
    {
        View::composer('home.index', HomeComposer::class);
    }

    public function viewData(): array
    {
        return [
            'navCategories' => Cache::remember('heritage.nav_categories', 600, fn () => Category::active()
                ->topLevel()->where('show_in_menu', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->get()),
        ];
    }

    public function defaults(): array
    {
        $u = fn (string $id, int $w, int $h) => Media::unsplash($id, $w, "&h={$h}");

        return [
            'site' => [
                'logo_primary' => 'Annapurna',
                'logo_accent' => 'Organics',
                'tagline' => 'Pure Indian pantry, delivered',
                'promo_messages' => ['Free pan-India delivery on eligible orders', 'Cash on delivery available', 'Festive offers — up to 30% off selected staples'],
                'delivery_note' => 'Pan-India delivery in 2–5 days',
                'search_placeholder' => 'Search rice, dals, spices, ghee…',
                'search_suggestions' => ['Basmati rice', 'Toor dal', 'A2 ghee', 'Turmeric', 'Almonds', 'Masala chai'],
                'support_phone' => null,
                'support_hours' => 'Mon – Sat, 9 am – 7 pm',
                'whatsapp_number' => null,
                'footer_blurb' => 'Single-origin staples, stone-ground flours, cold-pressed oils and heirloom spices — sourced directly from farmer collectives across India and packed fresh every week.',
                'certifications' => ['FSSAI licensed', 'India Organic certified', 'No artificial preservatives'],
                'show_diet_filter' => true,
                'announcement_cta_label' => 'Shop offers',
                'announcement_cta_url' => '/shop/sale',
            ],
            'home' => [
                'hero_slides' => [
                    ['eyebrow' => 'Harvest 2026', 'heading' => 'Pure ingredients. Timeless Indian taste.', 'text' => 'Heirloom grains, sun-dried spices and cold-pressed oils from farms we know by name.', 'cta_label' => 'Shop the pantry', 'cta_url' => '/shop', 'secondary_label' => 'Our story', 'secondary_url' => '/about', 'image' => $u('1532336414038-cf19250c5757', 1600, 1000), 'align' => 'left'],
                    ['eyebrow' => 'Festive season', 'heading' => 'Ghee, dry fruits and sweets for the celebrations.', 'text' => 'Curated gift boxes and premium staples — up to 30% off until Diwali.', 'cta_label' => 'Shop festive offers', 'cta_url' => '/shop/sale', 'secondary_label' => 'Gift boxes', 'secondary_url' => '/shop/gift-boxes', 'image' => $u('1512909006721-3d6018887383', 1600, 1000), 'align' => 'left'],
                    ['eyebrow' => 'From the fields', 'heading' => 'Single-origin rice, milled to order.', 'text' => 'Aged basmati, red rice and millets from cooperative farms in Punjab, Kerala and Karnataka.', 'cta_label' => 'Shop rice & grains', 'cta_url' => '/shop/rice-grains', 'secondary_label' => null, 'secondary_url' => null, 'image' => $u('1500382017468-9049fed747ef', 1600, 1000), 'align' => 'left'],
                ],
                'sections' => [
                    ['key' => 'categories', 'enabled' => true],
                    ['key' => 'featured', 'enabled' => true],
                    ['key' => 'offer', 'enabled' => true],
                    ['key' => 'bestsellers', 'enabled' => true],
                    ['key' => 'story', 'enabled' => true],
                    ['key' => 'needs', 'enabled' => true],
                    ['key' => 'brands', 'enabled' => true],
                    ['key' => 'testimonials', 'enabled' => true],
                    ['key' => 'journal', 'enabled' => true],
                    ['key' => 'trust', 'enabled' => true],
                ],
                'categories_eyebrow' => 'Shop by category',
                'categories_heading' => 'Everything a good Indian kitchen needs',
                'categories_limit' => 10,
                'featured_eyebrow' => 'Featured',
                'featured_heading' => 'This week’s picks',
                'featured_text' => 'New harvests, small-batch spices and pantry favourites chosen by our team.',
                'featured_source' => 'new',
                'featured_collection_slug' => null,
                'featured_limit' => 8,
                'offer_eyebrow' => 'Special offers',
                'offer_heading' => 'Up to 30% off pantry essentials',
                'offer_text' => 'Stock up on rice, dals, atta and ghee — prices drop every Monday and stay low all week.',
                'offer_cta_label' => 'Shop offers',
                'offer_cta_url' => '/shop/sale',
                'offer_image' => $u('1509358271058-acd22cc93898', 1200, 1200),
                'offer_badge' => 'Up to 30% off',
                'bestsellers_eyebrow' => 'Most loved',
                'bestsellers_heading' => 'Bestsellers in Indian homes',
                'bestsellers_limit' => 8,
                'story_eyebrow' => 'Our heritage',
                'story_heading' => 'Grown by farmers we know. Packed the week it arrives.',
                'story_text' => "Every grain, dal and spice comes from small farmer collectives across twelve Indian states — sun-dried, stone-ground and cold-pressed the way our grandmothers did it. No refined additives, no artificial colours, nothing you couldn’t find in a village kitchen.\n\nWe pay farmers above market rates, test every batch in an FSSAI-approved lab, and print the harvest and origin on every pack.",
                'story_image' => $u('1464226184884-fa280b87c399', 1200, 1500),
                'story_image_secondary' => $u('1500382017468-9049fed747ef', 900, 700),
                'story_cta_label' => 'Read our story',
                'story_cta_url' => '/about',
                'story_stats' => [
                    ['value' => '1,200+', 'label' => 'Partner farmers'],
                    ['value' => '12', 'label' => 'Indian states'],
                    ['value' => '100%', 'label' => 'Lab-tested batches'],
                ],
                'needs_eyebrow' => 'Shop by need',
                'needs_heading' => 'Built around how you cook',
                'needs_limit' => 6,
                'brands_eyebrow' => 'Popular brands',
                'brands_heading' => 'Houses we trust',
                'brands' => [],
                'testimonials_eyebrow' => 'Customer stories',
                'testimonials_heading' => 'Loved in kitchens across India',
                'testimonials' => [
                    ['name' => 'Meera Krishnan', 'location' => 'Chennai', 'rating' => 5, 'text' => 'The aged basmati is the best I have cooked with — long, fragrant grains that never turn sticky. The whole family noticed.', 'product' => 'Aged Basmati Rice'],
                    ['name' => 'Arjun Desai', 'location' => 'Pune', 'rating' => 5, 'text' => 'Finally an A2 ghee that tastes like the one from my grandmother’s village. Granular, nutty and honest.', 'product' => 'A2 Gir Cow Ghee'],
                    ['name' => 'Farah Siddiqui', 'location' => 'Lucknow', 'rating' => 4, 'text' => 'Spices arrived within three days and the garam masala is incredibly fresh — you can smell the cardamom through the pack.', 'product' => 'Stone-ground Garam Masala'],
                ],
                'journal_eyebrow' => 'Recipes & stories',
                'journal_heading' => 'From our kitchen journal',
                'trust_items' => [
                    ['icon' => 'badge', 'title' => '100% quality products', 'text' => 'Every batch lab-tested and traceable to its farm.'],
                    ['icon' => 'lock', 'title' => 'Secure payments', 'text' => 'UPI, cards, wallets, net banking and cash on delivery.'],
                    ['icon' => 'truck', 'title' => 'Pan-India delivery', 'text' => 'Free above ₹999. 2–5 days to most pincodes.'],
                    ['icon' => 'rotate', 'title' => 'Easy returns', 'text' => 'Damaged or not as described? Replaced within 7 days.'],
                    ['icon' => 'leaf', 'title' => 'Trusted ingredients', 'text' => 'No artificial colours, preservatives or refined additives.'],
                ],
                'newsletter_eyebrow' => 'Stay in touch',
                'newsletter_heading' => 'Recipes, harvest news and ₹100 off your first order',
                'newsletter_text' => 'One email a week. Unsubscribe whenever you like.',
            ],
        ];
    }
}
