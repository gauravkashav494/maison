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

    public function pwa(): array
    {
        return ['name' => trim($this->setting('site.logo_primary', 'Annapurna').' '.$this->setting('site.logo_accent', '')), 'theme_color' => '#FFFCF5', 'background_color' => '#FFFCF5'];
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
                'logo_sub' => 'Organics',
                'tagline' => 'Pure Indian pantry, delivered',
                'promo_messages' => ['COD available', 'Free delivery across India on eligible orders', 'Festive Sale is live — up to 30% off selected staples', 'Mystery gift on orders above ₹1,499'],
                'delivery_note' => 'Pan-India delivery in 2–5 days',
                'search_placeholder' => 'Search',
                'search_suggestions' => ['Basmati rice', 'Toor dal', 'A2 ghee', 'Turmeric', 'Almonds', 'Masala chai'],
                'nav_featured_label' => 'Festive Special',
                'nav_featured_url' => '/collections/festive-cooking',
                'nav_all_label' => 'All Products',
                'nav_category_label' => 'Shop By Category',
                'nav_need_label' => 'Shop By Need',
                'support_phone' => null,
                'support_toll_free' => '1800-000-0000',
                'support_hours' => '10 AM – 7 PM (Monday to Saturday)',
                'support_email' => null,
                'gifting_email' => null,
                'whatsapp_number' => null,
                'footer_blurb' => 'Single-origin staples, stone-ground flours, cold-pressed oils and heirloom spices — sourced directly from farmer collectives across India.',
                'certifications' => ['FSSAI licensed', 'India Organic certified', 'No artificial preservatives'],
                'show_diet_filter' => true,
            ],
            'home' => [
                'hero_slides' => [
                    ['eyebrow' => 'Harvest 2026', 'heading' => 'Pure ingredients. Timeless Indian taste.', 'text' => 'Heirloom grains, sun-dried spices and cold-pressed oils from farms we know by name.', 'cta_label' => '', 'cta_url' => '/shop', 'image' => $u('1532336414038-cf19250c5757', 1800, 800), 'badges' => ['Stone-ground & cold-pressed', 'Farmer-direct sourcing', 'Lab-tested batches', 'Nothing artificial']],
                    ['eyebrow' => 'Festive season', 'heading' => 'Ghee, dry fruits and sweets for the celebrations.', 'text' => 'Curated gift boxes and premium staples — up to 30% off until Diwali.', 'cta_label' => '', 'cta_url' => '/shop/sale', 'image' => $u('1512909006721-3d6018887383', 1800, 800), 'badges' => ['Reusable gift tins', 'Hand-packed', 'Pan-India delivery', 'Custom notes']],
                    ['eyebrow' => 'From the fields', 'heading' => 'Single-origin rice, milled to order.', 'text' => 'Aged basmati, red rice and millets from cooperative farms in Punjab, Kerala and Karnataka.', 'cta_label' => '', 'cta_url' => '/shop/rice-grains', 'image' => $u('1536304993881-ff6e9eefa2a6', 1800, 800), 'badges' => ['Aged 24 months', 'Milled weekly', 'Single origin', 'Low GI options']],
                ],
                'sections' => [
                    ['key' => 'strip', 'enabled' => true],
                    ['key' => 'featured', 'enabled' => true],
                    ['key' => 'combos', 'enabled' => true],
                    ['key' => 'bestsellers', 'enabled' => true],
                    ['key' => 'video', 'enabled' => true],
                    ['key' => 'needs', 'enabled' => true],
                    ['key' => 'new', 'enabled' => true],
                    ['key' => 'certifications', 'enabled' => true],
                    ['key' => 'trust', 'enabled' => true],
                    ['key' => 'categories', 'enabled' => true],
                    ['key' => 'values', 'enabled' => true],
                    ['key' => 'offer', 'enabled' => false],
                    ['key' => 'journal', 'enabled' => true],
                    ['key' => 'testimonials', 'enabled' => true],
                ],
                'strip_text' => 'We stone-grind, cold-press and sun-dry — never refine, bleach or polish — so every pack keeps the whole grain’s goodness.',
                'strip_image' => $u('1506368249639-73a05d6f6488', 1600, 300),
                'featured_heading' => 'Festive Special',
                'featured_source' => 'collection',
                'featured_collection_slug' => 'festive-cooking',
                'featured_limit' => 10,
                'combos_heading' => 'Super Saver Combos',
                'combos_collection_slug' => 'everyday-essentials',
                'combos_limit' => 10,
                'bestsellers_heading' => 'Our Best Sellers',
                'bestsellers_limit' => 10,
                'video_url' => null,
                'video_poster' => $u('1556909212-d5b604d0c90d', 1600, 800),
                'needs_heading' => 'Shop By Need',
                'needs_limit' => 8,
                'new_heading' => 'New Arrivals',
                'new_limit' => 10,
                'certifications_heading' => 'Our Certifications',
                'certifications' => [['label' => 'FSSAI Licensed'], ['label' => 'India Organic'], ['label' => 'NPOP Certified'], ['label' => 'Jaivik Bharat'], ['label' => 'ISO 22000'], ['label' => 'Non-GMO'], ['label' => 'Lab Tested'], ['label' => 'Fair Trade']],
                'trust_heading' => 'Benefit From Choosing The Best',
                'trust_image' => $u('1464226184884-fa280b87c399', 1000, 900),
                'trust_items' => [
                    ['icon' => 'badge', 'title' => '100% Quality Products', 'text' => 'Every batch lab-tested'],
                    ['icon' => 'lock', 'title' => 'Secure Payments', 'text' => 'UPI, cards, wallets, COD'],
                    ['icon' => 'truck', 'title' => 'Pan-India Delivery', 'text' => '19,000+ pincodes'],
                    ['icon' => 'rotate', 'title' => 'Easy Returns', 'text' => '7-day quality promise'],
                    ['icon' => 'leaf', 'title' => 'Trusted Ingredients', 'text' => 'Nothing artificial, ever'],
                    ['icon' => 'star', 'title' => '1,200+ Farmers', 'text' => 'Paid above market rates'],
                ],
                'categories_heading' => 'Our Range Of Categories',
                'categories_limit' => 10,
                'offer_heading' => 'Conscious Gifting, Redefined',
                'offer_text' => 'Festive hampers of dry fruits, ghee, spices and teas in reusable tins — up to 30% off this season.',
                'offer_cta_label' => 'Shop gift boxes',
                'offer_cta_url' => '/shop/gift-boxes',
                'offer_image' => $u('1512909006721-3d6018887383', 1600, 800),
                'offer_badge' => 'Up to 30% off',
                'values_strip' => [['icon' => 'badge', 'label' => 'FSSAI-approved facilities'], ['icon' => 'diamond', 'label' => 'Made in India'], ['icon' => 'leaf', 'label' => 'Eco-friendly packaging'], ['icon' => 'shield', 'label' => 'Ethical sourcing'], ['icon' => 'star', 'label' => '100% natural'], ['icon' => 'check', 'label' => 'Non-GMO & unrefined']],
                'journal_heading' => 'Blogs',
                'testimonials_heading' => '500+ Happy Kitchens',
                'testimonials_image' => $u('1556910103-1c02745aae4d', 800, 1000),
                'testimonials' => [
                    ['name' => 'Meera', 'location' => 'Chennai', 'rating' => 5, 'text' => 'The aged basmati is the best I have cooked with — long, fragrant grains that never turn sticky.', 'product' => 'Aged Basmati Rice'],
                    ['name' => 'Arjun', 'location' => 'Pune', 'rating' => 5, 'text' => 'Finally an A2 ghee that tastes like the one from my grandmother’s village. Granular, nutty and honest.', 'product' => 'A2 Gir Cow Ghee'],
                    ['name' => 'Farah', 'location' => 'Lucknow', 'rating' => 4, 'text' => 'Spices arrived within three days and the garam masala is incredibly fresh — you can smell the cardamom through the pack.', 'product' => 'Garam Masala'],
                    ['name' => 'Siddharth', 'location' => 'Mumbai', 'rating' => 5, 'text' => 'The atta makes soft rotis that stay soft till dinner. Milling date on the pack is a nice touch.', 'product' => 'Whole Wheat Atta'],
                    ['name' => 'Priya', 'location' => 'Bengaluru', 'rating' => 5, 'text' => 'Honey crystallised in winter and they had already explained why on the jar. Tastes like real forest honey.', 'product' => 'Raw Forest Honey'],
                    ['name' => 'Nikhil', 'location' => 'Noida', 'rating' => 4, 'text' => 'Packaging is clean, the dal cooks quickly and it is unpolished as promised. Will reorder.', 'product' => 'Toor Dal'],
                ],
                'story_heading' => 'Our Farmers Are the Heart of Our Purpose',
                'story_text' => "Every grain, dal and spice comes from small farmer collectives across twelve Indian states. We agree prices above the mandi rate before sowing, pay within seven days of delivery and fund natural-farming training.\n\nWhen farmers thrive, the food on your table is better — and so is the land it came from.",
                'story_image' => $u('1464226184884-fa280b87c399', 1000, 1200),
                'story_cta_label' => 'Learn more',
                'story_cta_url' => '/about',
                'story_stats' => [['value' => '1,200+', 'label' => 'Partner farmers'], ['value' => '12', 'label' => 'Indian states'], ['value' => '100%', 'label' => 'Lab-tested batches'], ['value' => '10+', 'label' => 'Years of sourcing']],
                'newsletter_heading' => 'Sign Up To Get Updates',
                'newsletter_text' => 'Recipes, harvest news and ₹100 off your first order.',
            ],
        ];
    }
}
