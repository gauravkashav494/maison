<?php

namespace App\Templates\Plumbing;

use App\Filament\Pages\PlumbingHomepageSettings;
use App\Filament\Pages\PlumbingSettings;
use App\Models\Category;
use App\Models\Setting;
use App\Support\Media;
use App\Templates\Template;
use Database\Seeders\PlumbingCatalogSeeder;
use Database\Seeders\PlumbingContentSeeder;
use Database\Seeders\PlumbingMenuSeeder;
use Database\Seeders\PlumbingSettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

/**
 * Indian plumbing & sanitaryware storefront: blue-led, product-first design with a
 * prominent search, category mega menu, project-based shopping, deals, bulk-quote
 * band, specification tables and a mobile layout built for Indian shoppers.
 */
class PlumbingTemplate extends Template
{
    public function id(): string
    {
        return 'plumbing';
    }

    public function name(): string
    {
        return 'Plumbing';
    }

    public function description(): string
    {
        return 'Indian plumbing & sanitaryware store — blue-led, category-first storefront with a large search, project-based shopping, daily deals, bulk-quote requests, specification tables, GST/COD messaging and a mobile-first layout.';
    }

    public function thumbnail(): ?string
    {
        return '/templates/plumbing/thumbnail.jpg';
    }

    public function pwa(): array
    {
        return ['name' => $this->setting('site.logo_primary', 'Plumb').$this->setting('site.logo_accent', 'Kart'), 'theme_color' => '#063B73', 'background_color' => '#F7F9FC'];
    }

    public function viewPath(): ?string
    {
        return 'templates/plumbing';
    }

    public function assets(): array
    {
        return ['resources/templates/plumbing/css/app.css', 'resources/templates/plumbing/js/app.js'];
    }

    public function menuLocations(): array
    {
        return [
            'header' => ['plumbing_header', 'Header — Category navigation'],
            'footer_shop' => ['plumbing_footer_shop', 'Footer — Shop'],
            'footer_support' => ['plumbing_footer_support', 'Footer — Customer support'],
            'footer_company' => ['plumbing_footer_company', 'Footer — Company'],
            'legal' => ['plumbing_legal', 'Footer — Policies'],
        ];
    }

    public function settingGroups(): array
    {
        return ['site' => 'plumbing_site', 'home' => 'plumbing_home'];
    }

    public function adminPages(): array
    {
        return [PlumbingSettings::class, PlumbingHomepageSettings::class];
    }

    public function isInstalled(): bool
    {
        return Setting::get('plumbing_home') !== null && Category::where('template', 'plumbing')->exists();
    }

    public function install(): void
    {
        app(PlumbingSettingsSeeder::class)->run();
        app(PlumbingMenuSeeder::class)->run();
        app(PlumbingCatalogSeeder::class)->run();
        app(PlumbingContentSeeder::class)->run();
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
            'navCategories' => Cache::remember('plumbing.nav_categories', 600, fn () => Category::active()
                ->topLevel()->where('show_in_menu', true)
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->get()),
        ];
    }

    public function defaults(): array
    {
        $u = fn (string $id, int $w, int $h) => Media::unsplash($id, $w, "&h={$h}");

        return [
            'site' => [
                'logo_primary' => 'Plumb',
                'logo_accent' => 'Kart',
                'tagline' => 'India’s online plumbing & sanitaryware store',
                'usp_strip' => ['Pan-India delivery', 'Genuine products', 'Easy returns', 'Expert support'],
                'search_placeholder' => 'Search pipes, fittings, taps, valves, pumps…',
                'search_suggestions' => ['1 inch PVC pipe', 'CPVC elbow', 'basin mixer', 'brass ball valve', '1 HP water pump', 'wall mixer', 'PVC tee'],
                'support_phone' => '+91 98765 43210',
                'support_hours' => 'Mon–Sat, 9 am – 7 pm',
                'whatsapp_number' => '919876543210',
                'support_email' => 'support@plumbkart.in',
                'delivery_promise' => 'Dispatch in 24 hours · Delivery in 2–6 days',
                'gst_note' => 'All prices inclusive of GST. GST invoice available on every order.',
                'bulk_heading' => 'Buying for a project?',
                'bulk_text' => 'Contractors, plumbers and builders get better pricing on bulk requirements — pipes by the bundle, fittings by the box, sanitaryware for whole buildings.',
                'bulk_cta_label' => 'Request bulk quote',
                'bulk_cta_url' => '/contact?subject=Bulk+quote',
                'bulk_points' => ['Volume pricing on 50+ units', 'Dedicated project executive', 'Scheduled site deliveries', 'GST invoice & credit terms'],
                'footer_blurb' => 'PlumbKart supplies genuine pipes, fittings, sanitaryware, taps, valves and pumps from India’s trusted brands — delivered to homes, sites and shops across the country.',
                'newsletter_heading' => 'Get offers & new arrivals',
                'newsletter_text' => 'Deals on plumbing essentials, straight to your inbox. No spam.',
            ],
            'home' => [
                'sections' => [
                    ['key' => 'categories', 'enabled' => true],
                    ['key' => 'deals', 'enabled' => true],
                    ['key' => 'projects', 'enabled' => true],
                    ['key' => 'featured', 'enabled' => true],
                    ['key' => 'promos', 'enabled' => true],
                    ['key' => 'bestsellers', 'enabled' => true],
                    ['key' => 'bulk', 'enabled' => true],
                    ['key' => 'brands', 'enabled' => true],
                    ['key' => 'new', 'enabled' => true],
                    ['key' => 'why', 'enabled' => true],
                    ['key' => 'faq', 'enabled' => false],
                ],
                'hero_eyebrow' => 'Pipes · Fittings · Sanitaryware · Pumps',
                'hero_heading' => 'Everything you need for every plumbing job',
                'hero_text' => 'Quality pipes, fittings, faucets, valves and plumbing essentials from trusted Indian brands — at competitive prices, delivered pan-India.',
                'hero_primary_label' => 'Shop products',
                'hero_primary_url' => '/shop',
                'hero_secondary_label' => 'Explore categories',
                'hero_secondary_url' => '#categories',
                'hero_image' => $u('1749532125405-70950966b0e5', 1200, 1000),
                'hero_image_alt' => 'Plumbing fittings laid out on a blueprint',
                'hero_stats' => [['value' => '5,000+', 'label' => 'Products'], ['value' => '40+', 'label' => 'Brands'], ['value' => '18,000+', 'label' => 'Pincodes served']],
                'hero_tiles' => [
                    ['title' => 'Pipes & Fittings', 'text' => 'PVC · CPVC · UPVC', 'url' => '/shop/pipes-fittings', 'image' => $u('1718347791747-35fac02b585e', 600, 600)],
                    ['title' => 'Taps & Faucets', 'text' => 'Basin · Wall · Kitchen', 'url' => '/shop/taps-faucets', 'image' => $u('1542855368-ca6ea825bca2', 600, 600)],
                ],
                'categories_heading' => 'Shop by category',
                'categories_text' => 'Everything from a single elbow to a full bathroom.',
                'categories_limit' => 9,
                'deals_heading' => 'Today’s plumbing deals',
                'deals_text' => 'Genuine products, honest discounts — prices reset at midnight.',
                'deals_limit' => 8,
                'projects_heading' => 'Shop for your project',
                'projects_text' => 'Start from the job, not the catalogue.',
                'projects' => [
                    ['title' => 'Bathroom', 'text' => 'Taps, showers, sanitaryware & concealed fittings', 'url' => '/shop/bathroom', 'image' => $u('1584622650111-993a426fbf0a', 800, 600)],
                    ['title' => 'Kitchen', 'text' => 'Sink mixers, drain kits & water connections', 'url' => '/shop/taps-faucets', 'image' => $u('1757787697646-e84d5490add1', 800, 600)],
                    ['title' => 'Water supply', 'text' => 'CPVC/UPVC lines, valves, tanks & pumps', 'url' => '/shop/pipes-fittings', 'image' => $u('1784972857429-3707b41367dd', 800, 600)],
                    ['title' => 'Drainage', 'text' => 'SWR pipes, traps, gratings & drain covers', 'url' => '/shop/drainage', 'image' => $u('1545193329-4a052e14eb8f', 800, 600)],
                    ['title' => 'Home plumbing', 'text' => 'Complete kits for new homes & renovations', 'url' => '/shop', 'image' => $u('1676210134188-4c05dd172f89', 800, 600)],
                    ['title' => 'Commercial', 'text' => 'Bulk pipes, pumps & fixtures for buildings', 'url' => '/contact?subject=Bulk+quote', 'image' => $u('1778090533461-c5c2f29637a8', 800, 600)],
                    ['title' => 'Construction', 'text' => 'Site supply for builders & contractors', 'url' => '/contact?subject=Bulk+quote', 'image' => $u('1632201147654-f6f54427e538', 800, 600)],
                    ['title' => 'Repair & maintenance', 'text' => 'Tools, sealants, spares & quick fixes', 'url' => '/shop/tools-hardware', 'image' => $u('1503789146722-cf137a3c0fea', 800, 600)],
                ],
                'featured_heading' => 'Featured products',
                'featured_text' => 'Hand-picked essentials our customers reorder most.',
                'featured_limit' => 10,
                'promos' => [
                    ['eyebrow' => 'Bathroom fittings', 'heading' => 'Complete the bathroom, in one order', 'text' => 'Mixers, showers, health faucets and accessories from Jaquar, Hindware & Cera.', 'cta_label' => 'Shop bathroom', 'url' => '/shop/bathroom', 'image' => $u('1652662700928-5a4685e87d64', 900, 700), 'tone' => 'blue'],
                    ['eyebrow' => 'Water pumps', 'heading' => 'Pumps that keep the pressure up', 'text' => 'Self-priming, submersible and openwell pumps from 0.5 HP to 2 HP.', 'cta_label' => 'Shop pumps', 'url' => '/shop/water-pumps', 'image' => $u('1534641614095-6222aed9bdd6', 900, 700), 'tone' => 'orange'],
                ],
                'bestsellers_heading' => 'Bestsellers',
                'bestsellers_text' => 'What plumbers across India keep in the van.',
                'bestsellers_limit' => 10,
                'brands_heading' => 'Trusted brands',
                'brands_text' => 'Genuine stock, sourced directly from manufacturers and authorised distributors.',
                'new_heading' => 'New arrivals',
                'new_text' => 'Fresh additions to the catalogue.',
                'new_limit' => 10,
                'why_heading' => 'Why buy from PlumbKart',
                'why_items' => [
                    ['icon' => 'badge', 'title' => 'Genuine products', 'text' => 'Only authorised brands, with manufacturer warranty.'],
                    ['icon' => 'truck', 'title' => 'Fast delivery', 'text' => 'Dispatch within 24 hours, delivery across 18,000+ pincodes.'],
                    ['icon' => 'tag', 'title' => 'Competitive pricing', 'text' => 'Trade prices online, volume discounts for projects.'],
                    ['icon' => 'shield', 'title' => 'Secure payments', 'text' => 'UPI, cards, net banking, wallets and cash on delivery.'],
                    ['icon' => 'headset', 'title' => 'Expert support', 'text' => 'Talk to a plumbing expert on phone or WhatsApp.'],
                    ['icon' => 'rotate', 'title' => 'Easy returns', 'text' => '7-day returns on unused products in original packing.'],
                ],
                'faq_heading' => 'Frequently asked questions',
            ],
        ];
    }
}
