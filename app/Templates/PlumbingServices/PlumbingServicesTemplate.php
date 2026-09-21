<?php

namespace App\Templates\PlumbingServices;

use App\Filament\Pages\PlumbingServicesHomepageSettings;
use App\Filament\Pages\PlumbingServicesSettings;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Setting;
use App\Support\Media;
use App\Templates\Template;
use Database\Seeders\PlumbingServicesContentSeeder;
use Database\Seeders\PlumbingServicesMenuSeeder;
use Database\Seeders\PlumbingServicesSettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

/**
 * Indian plumbing SERVICE business (not a store): lead-generation site with service
 * discovery, problem-first navigation, online booking, call / WhatsApp actions and
 * local service-area pages. Desktop is a professional company website; on phones it
 * behaves like a service app (location header, big search, category rail, quick
 * actions, bottom navigation, sticky call/book bar, bottom sheets).
 */
class PlumbingServicesTemplate extends Template
{
    public const ID = 'plumbing-services';

    public function id(): string
    {
        return self::ID;
    }

    public function name(): string
    {
        return 'Plumbing Services';
    }

    public function description(): string
    {
        return 'Indian plumbing service company — services, problem-based discovery, online booking, call & WhatsApp leads, emergency help, service-area pages, reviews and before/after work. App-like on mobile, professional website on desktop. No products or cart.';
    }

    public function thumbnail(): ?string
    {
        return '/templates/plumbing-services/thumbnail.jpg';
    }

    /** Templates that render service pages instead of the catalogue opt in here. */
    public function supportsServices(): bool
    {
        return true;
    }

    public function pwa(): array
    {
        return ['name' => $this->setting('site.business_name', 'PipeCare Plumbing'), 'theme_color' => '#0757A0', 'background_color' => '#F6FAFE'];
    }

    public function viewPath(): ?string
    {
        return 'templates/plumbing-services';
    }

    public function assets(): array
    {
        return ['resources/templates/plumbing-services/css/app.css', 'resources/templates/plumbing-services/js/app.js'];
    }

    public function menuLocations(): array
    {
        return [
            'header' => ['plumbing_services_header', 'Header — Main navigation'],
            'footer_services' => ['plumbing_services_footer_services', 'Footer — Services'],
            'footer_company' => ['plumbing_services_footer_company', 'Footer — Company'],
            'footer_help' => ['plumbing_services_footer_help', 'Footer — Help'],
            'legal' => ['plumbing_services_legal', 'Footer — Policies'],
        ];
    }

    public function settingGroups(): array
    {
        return ['site' => 'plumbing_services_site', 'home' => 'plumbing_services_home'];
    }

    public function adminPages(): array
    {
        return [PlumbingServicesSettings::class, PlumbingServicesHomepageSettings::class];
    }

    public function isInstalled(): bool
    {
        return Setting::get('plumbing_services_home') !== null && Service::where('template', self::ID)->exists();
    }

    public function install(): void
    {
        app(PlumbingServicesSettingsSeeder::class)->run();
        app(PlumbingServicesMenuSeeder::class)->run();
        app(PlumbingServicesContentSeeder::class)->run();
        Cache::flush();
    }

    public function boot(): void
    {
        View::composer('home.index', HomeComposer::class);
    }

    /** Phone / WhatsApp / email links used by every Call, WhatsApp and contact element. */
    public function contact(): array
    {
        $site = $this->setting('site', []);
        $digits = fn (?string $v) => preg_replace('/[^0-9+]/', '', (string) $v);
        $phone = $site['phone'] ?? '';
        $emergency = $site['emergency_phone'] ?: $phone;
        $wa = $digits($site['whatsapp_number'] ?? '');

        return [
            'name' => $site['business_name'] ?? setting('site.name'),
            'phone' => $phone,
            'phone_href' => 'tel:'.$digits($phone),
            'emergency_phone' => $emergency,
            'emergency_href' => 'tel:'.$digits($emergency),
            'emergency_available' => (bool) ($site['emergency_available'] ?? false),
            'whatsapp' => $wa !== '',
            'whatsapp_href' => $wa !== '' ? 'https://wa.me/'.ltrim($wa, '+').'?text='.rawurlencode($site['whatsapp_message'] ?? '') : '#',
            'email' => $site['email'] ?? '',
            'email_href' => 'mailto:'.($site['email'] ?? ''),
            'address' => $site['address'] ?? '',
            'hours' => $site['hours'] ?? '',
            'response' => $site['response_note'] ?? '',
            'default_area' => $site['default_area'] ?? '',
        ];
    }

    /** Service list for the header rail, mega menu, footer and booking flow; areas for the location picker. */
    public function viewData(): array
    {
        return [
            'biz' => $this->contact(),
            'navServices' => Cache::remember('plumbing_services.nav_services', 600, fn () => Service::active()
                ->get(['id', 'name', 'slug', 'icon', 'excerpt', 'is_emergency', 'is_popular'])),
            'navAreas' => Cache::remember('plumbing_services.nav_areas', 600, fn () => ServiceArea::active()
                ->get(['id', 'name', 'slug', 'state', 'response_time'])),
        ];
    }

    public function defaults(): array
    {
        $u = fn (string $id, int $w, int $h) => Media::unsplash($id, $w, "&h={$h}");

        return [
            'site' => [
                'business_name' => 'PipeCare Plumbing Services',
                'logo_primary' => 'Pipe',
                'logo_accent' => 'Care',
                'tagline' => 'Professional plumbers at your doorstep',
                'phone' => '+91 98765 43210',
                'emergency_phone' => '+91 98765 43210',
                'whatsapp_number' => '919876543210',
                'whatsapp_message' => 'Hi PipeCare, I need help with a plumbing problem.',
                'email' => 'help@pipecare.in',
                'address' => 'SCO 88, 3rd Floor, Feroze Gandhi Market, Ludhiana, Punjab 141001',
                'hours' => 'Mon–Sun, 8 am – 8 pm',
                'emergency_available' => true,
                'emergency_note' => '24×7 emergency plumbing for burst pipes, major leaks and blocked drains.',
                'response_note' => 'Plumber at your door in 60–90 minutes in most areas',
                'default_area' => 'Ludhiana',
                'usp_strip' => ['Verified plumbers', 'Transparent pricing', 'Same-day service', '30-day work warranty'],
                'search_placeholder' => 'Search plumbing services',
                'search_suggestions' => ['leak repair', 'bathroom plumber', 'drain cleaning', 'tap repair', 'emergency plumber', 'water tank cleaning'],
                'stats' => [
                    ['value' => '12+', 'label' => 'Years in service'],
                    ['value' => '25,000+', 'label' => 'Jobs completed'],
                    ['value' => '60', 'label' => 'Verified plumbers'],
                    ['value' => '4.8★', 'label' => 'Average rating'],
                ],
                'social' => [
                    ['network' => 'facebook', 'url' => '#'],
                    ['network' => 'instagram', 'url' => '#'],
                    ['network' => 'youtube', 'url' => '#'],
                ],
                'footer_blurb' => 'PipeCare provides professional plumbing services for homes, shops and offices across Punjab — leak repair, pipe work, drain cleaning, bathroom & kitchen plumbing, water tanks, pumps and 24×7 emergencies.',
                'footer_note' => 'Background-verified plumbers · GST invoice on every job · Genuine ISI-marked parts',
                'quote_heading' => 'Need a quote?',
                'quote_text' => 'Tell us about your plumbing requirement — a new bathroom, a full pipe replacement or regular maintenance — and we will explain the work involved and the expected cost before anyone visits.',
            ],
            'home' => [
                'sections' => [
                    ['key' => 'popular', 'enabled' => true],
                    ['key' => 'emergency', 'enabled' => true],
                    ['key' => 'problems', 'enabled' => true],
                    ['key' => 'how', 'enabled' => true],
                    ['key' => 'why', 'enabled' => true],
                    ['key' => 'areas', 'enabled' => true],
                    ['key' => 'reviews', 'enabled' => true],
                    ['key' => 'projects', 'enabled' => true],
                    ['key' => 'quote', 'enabled' => true],
                    ['key' => 'faq', 'enabled' => true],
                    ['key' => 'blog', 'enabled' => true],
                    ['key' => 'cta', 'enabled' => true],
                ],
                'hero_slides' => [
                    [
                        'eyebrow' => 'Trusted local plumbers',
                        'title' => 'Plumbing problems? We fix them fast.',
                        'text' => 'Professional plumbing services at your doorstep — leaks, pipes, drains, bathrooms, tanks and pumps.',
                        'image' => $u('1676210134188-4c05dd172f89', 1400, 900),
                        'cta_label' => 'Book a plumber',
                        'cta_url' => '/book',
                        'secondary_label' => 'Call now',
                        'secondary_action' => 'call',
                    ],
                    [
                        'eyebrow' => '24×7 emergency service',
                        'title' => 'Emergency plumbing, any hour.',
                        'text' => 'Burst pipe or major leak? Get professional help when you need it most.',
                        'image' => $u('1526898943670-92bfa9f94c12', 1400, 900),
                        'cta_label' => 'Get emergency help',
                        'cta_url' => '/emergency',
                        'secondary_label' => 'Call now',
                        'secondary_action' => 'call',
                    ],
                    [
                        'eyebrow' => 'Same-day visits',
                        'title' => 'Need a plumber today?',
                        'text' => 'Fast response, transparent pricing and a warranty on every job.',
                        'image' => $u('1542013936693-884638332954', 1400, 900),
                        'cta_label' => 'Schedule a service',
                        'cta_url' => '/book',
                        'secondary_label' => 'WhatsApp us',
                        'secondary_action' => 'whatsapp',
                    ],
                ],
                'quick_actions' => [
                    ['label' => 'Emergency', 'icon' => 'alert', 'url' => '/emergency', 'tone' => 'danger'],
                    ['label' => 'Book plumber', 'icon' => 'calendar', 'url' => '/book', 'tone' => 'primary'],
                    ['label' => 'Get quote', 'icon' => 'file', 'url' => '/quote', 'tone' => 'accent'],
                    ['label' => 'WhatsApp', 'icon' => 'whatsapp', 'action' => 'whatsapp', 'tone' => 'whatsapp'],
                ],
                'popular_heading' => 'Popular plumbing services',
                'popular_sub' => 'The jobs we are called for most — fixed by verified plumbers with a warranty.',
                'popular_limit' => 8,
                'emergency_heading' => 'Plumbing emergency?',
                'emergency_text' => 'Burst pipe? Major leakage? Blocked drain flooding the floor? Turn off the main valve and call us — our emergency team responds round the clock.',
                'emergency_points' => ['Response within 60 minutes', 'Available 24×7, including holidays', 'Temporary fix first, permanent repair next'],
                'problems_heading' => 'What’s the problem?',
                'problems_sub' => 'Not sure what the service is called? Pick what’s wrong and we will take it from there.',
                'problems' => [
                    ['label' => 'Water leakage', 'icon' => 'droplet', 'service' => 'leak-repair'],
                    ['label' => 'Blocked drain', 'icon' => 'drain', 'service' => 'drain-cleaning'],
                    ['label' => 'Low water pressure', 'icon' => 'gauge', 'service' => 'water-pressure-problems'],
                    ['label' => 'Broken tap', 'icon' => 'tap', 'service' => 'tap-faucet-repair'],
                    ['label' => 'Toilet problem', 'icon' => 'toilet', 'service' => 'toilet-repair'],
                    ['label' => 'Pipe burst', 'icon' => 'alert', 'service' => 'emergency-plumbing'],
                    ['label' => 'Bathroom leakage', 'icon' => 'bath', 'service' => 'bathroom-plumbing'],
                    ['label' => 'No water supply', 'icon' => 'pump', 'service' => 'water-pump-services'],
                    ['label' => 'Water tank problem', 'icon' => 'tank', 'service' => 'water-tank-services'],
                    ['label' => 'Geyser not working', 'icon' => 'flame', 'service' => 'geyser-water-heater-plumbing'],
                    ['label' => 'Kitchen sink issue', 'icon' => 'kitchen', 'service' => 'kitchen-plumbing'],
                    ['label' => 'Something else', 'icon' => 'help', 'service' => ''],
                ],
                'how_heading' => 'Getting a plumber is easy',
                'how_steps' => [
                    ['title' => 'Tell us your problem', 'text' => 'Pick a service or describe what’s wrong — online, on call or on WhatsApp.', 'icon' => 'chat'],
                    ['title' => 'Choose a time', 'text' => 'Today, tomorrow or a date that suits you. Morning, afternoon or evening.', 'icon' => 'calendar'],
                    ['title' => 'Our plumber visits', 'text' => 'A verified plumber arrives with the right tools and explains the fix and cost.', 'icon' => 'user-check'],
                    ['title' => 'Problem gets fixed', 'text' => 'Work is completed, tested and covered by our 30-day warranty.', 'icon' => 'check-badge'],
                ],
                'why_heading' => 'Why choose us?',
                'why_items' => [
                    ['title' => 'Experienced plumbers', 'text' => 'Background-verified, trained professionals with years of hands-on experience.', 'icon' => 'badge'],
                    ['title' => 'Fast response', 'text' => 'Same-day visits and a 60–90 minute emergency response in most areas.', 'icon' => 'bolt'],
                    ['title' => 'Transparent pricing', 'text' => 'You approve the estimate before work begins. No hidden charges.', 'icon' => 'rupee'],
                    ['title' => 'Quality work', 'text' => 'Genuine ISI-marked parts and a 30-day warranty on every job.', 'icon' => 'shield'],
                    ['title' => 'Reliable service', 'text' => 'On-time arrivals, clean work and a follow-up call after every visit.', 'icon' => 'clock'],
                    ['title' => 'Local support', 'text' => 'A local team that knows your area, your water supply and your building.', 'icon' => 'map-pin'],
                ],
                'areas_heading' => 'Plumbing services near you',
                'areas_sub' => 'Local teams across Punjab and Chandigarh Tricity.',
                'reviews_heading' => 'What our customers say',
                'reviews_limit' => 8,
                'projects_heading' => 'Recent plumbing work',
                'projects_sub' => 'Before and after — real jobs by our team.',
                'projects_limit' => 6,
                'faq_heading' => 'Frequently asked questions',
                'faq_limit' => 8,
                'blog_heading' => 'Tips & guides',
                'cta_heading' => 'Need a plumber?',
                'cta_text' => 'Tell us what’s wrong. We’ll help you get it fixed — today if possible.',
                'cta_image' => $u('1673870861507-d72aa6855d89', 1200, 800),
            ],
        ];
    }
}
