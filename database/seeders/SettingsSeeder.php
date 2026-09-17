<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Media;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('site', [
            'name' => 'Maison Élan',
            'logo_primary' => 'MAISON',
            'logo_accent' => 'Élan',
            'tagline' => 'Luxury fashion & lifestyle',
            'description' => 'Maison Élan — an edit of clothing, fragrance, timepieces, leather goods and accessories designed for the moments that define you.',
            'announcement_text' => 'Complimentary shipping on orders over ₹15,000',
            'announcement_text_short' => 'Free shipping over ₹15,000',
            'announcement_link_label' => 'Discover the New Season',
            'announcement_link_url' => '/collections/new-season',
            'free_shipping_threshold' => 15000,
            'contact_email' => 'care@maisonelan.com',
            'contact_phone' => '+91 80 4500 1200',
            'contact_hours' => 'Mon – Sat, 10:00 – 19:00 IST',
            'social_instagram' => 'https://instagram.com',
            'social_pinterest' => 'https://pinterest.com',
            'social_facebook' => 'https://facebook.com',
            'social_youtube' => 'https://youtube.com',
            'payment_methods' => ['Visa', 'Mastercard', 'Amex', 'UPI', 'RuPay', 'PayPal', 'Apple Pay', 'G Pay'],
            'footer_blurb' => 'Clothing, fragrance, timepieces and leather goods designed with intention and made to last beyond the season.',
            'footer_newsletter_heading' => 'Early access, private sales and notes from the atelier.',
            'page_header_image' => Media::unsplash('1558769132-cb1aea458c5e', 2000, '&h=900'),
        ]);

        Setting::set('seo', [
            'default_title' => 'Maison Élan — Luxury fashion & lifestyle',
            'title_suffix' => ' — Maison Élan',
            'default_description' => 'Clothing, fragrance, timepieces, leather goods and accessories designed for the moments that define you.',
            'default_og_image' => Media::unsplash('1524504388940-b1c1722653e1', 1200, '&h=630'),
            'google_site_verification' => null,
            'gtag_id' => null,
            'robots_extra' => null,
            'discourage_indexing' => true, // switch off in Site settings → SEO defaults when going live
        ]);

        Setting::set('home', [
            'hero_eyebrow' => 'Autumn / Winter 2026 — The New Season',
            'hero_eyebrow_short' => 'AW 2026 — New Season',
            'hero_heading' => "The quiet\n*confidence* of\ndressing well.",
            'hero_text' => 'Clothing, fragrance, timepieces and leather goods designed with intention — for the moments that define you.',
            'hero_primary_label' => 'Shop Collection',
            'hero_primary_url' => '/collections/new-season',
            'hero_secondary_label' => 'Explore New Arrivals',
            'hero_secondary_url' => '/shop/new-arrivals',
            'hero_image' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?w=1600&q=80&auto=format&fit=crop&h=2000&crop=faces',
            'hero_image_alt' => 'Autumn / Winter campaign — a model in a sheer black blouse against a charcoal backdrop',
            'hero_caption' => 'Campaign 01 / Shot in Marseille',
            'hero_look_title' => 'Look 01',
            'hero_look_text' => 'Sheer silk blouse — Signature Collection',

            'ticker_items' => ['Clothing', 'Perfumes', 'Watches', 'Bags', 'Belts', 'Accessories'],

            'categories_eyebrow' => 'Explore',
            'categories_heading' => 'Six categories. One point of view.',
            'categories_text' => 'From the wardrobe to the vanity to the wrist — every piece carries the same restraint and attention to material.',

            'arrivals_eyebrow' => 'Just in',
            'arrivals_heading' => 'New Arrivals',
            'arrivals_text' => 'The latest additions to the edit — arriving weekly, in limited quantities.',
            'arrivals_limit' => 8,

            'editorial_eyebrow' => 'The Season',
            'editorial_heading' => 'Designed for the moments that *define* you.',
            'editorial_text' => 'We begin every collection with a question: what will you reach for, again and again? The answer is rarely loud. It is the coat with the perfect drape, the scent that becomes yours, the watch that outlives trends. Pieces made carefully, in small runs, from materials chosen for how they age.',
            'editorial_cta_label' => 'Discover the Collection',
            'editorial_cta_url' => '/collections/new-season',
            'editorial_image' => Media::unsplash('1526413232644-8a40f03cc03b', 1400, '&h=1700'),
            'editorial_image_secondary' => Media::unsplash('1571513722275-4b41940f54b8', 900, '&h=1200'),
            'editorial_caption' => 'Campaign 02 — Editorial',
            'editorial_stats' => [
                ['value' => '48', 'label' => 'New pieces'],
                ['value' => '6', 'label' => 'Categories'],
                ['value' => '3', 'label' => 'Ateliers'],
            ],

            'featured_collection_id' => Collection::where('slug', 'signature')->value('id'),
            'featured_eyebrow' => 'The House Signature',
            'featured_text_extra' => 'Cut in Italian wool, lined in silk, finished by hand.',
            'featured_secondary_label' => 'Read the Story',
            'featured_secondary_url' => '/journal/the-signature-collection',

            'bestsellers_eyebrow' => 'Most loved',
            'bestsellers_heading' => 'Best Sellers',
            'bestsellers_text' => 'The pieces our clients return to — season after season.',

            'fragrance_product_id' => Product::where('slug', 'amber-oud-eau-de-parfum')->value('id'),
            'fragrance_eyebrow' => 'Fragrance Spotlight',
            'fragrance_heading' => 'Amber *Oud*',
            'fragrance_subheading' => 'Eau de Parfum · 50ml / 100ml',
            'fragrance_text' => 'A composition that unfolds slowly: a bright, peppered opening giving way to smoked oud and rose, resting on a base of amber and sandalwood that lingers well into the evening. Created in Grasse with master perfumer Élodie Marchand.',
            'fragrance_image' => Media::unsplash('1622618991746-fe6004db3a47', 1600, '&h=2000'),
            'fragrance_caption' => 'Fragrance — N° 01',
            'fragrance_notes' => [
                ['label' => 'Top', 'value' => 'Pink pepper, bergamot, saffron'],
                ['label' => 'Heart', 'value' => 'Smoked oud, Bulgarian rose'],
                ['label' => 'Base', 'value' => 'Amber, Siam benzoin, sandalwood'],
            ],
            'fragrance_size' => '100ml',

            'journal_eyebrow' => 'The Journal',
            'journal_heading' => 'Notes on style, craft and the season.',

            'promises' => [
                ['icon' => 'badge-check', 'title' => 'Authentic Products', 'text' => 'Every piece is sourced directly from our ateliers and partner houses. Guaranteed.'],
                ['icon' => 'gem', 'title' => 'Premium Quality', 'text' => 'Natural fibres, full-grain leathers and Swiss movements — chosen for how they age.'],
                ['icon' => 'lock', 'title' => 'Secure Payments', 'text' => '256-bit encrypted checkout with cards, UPI, wallets and cash on delivery.'],
                ['icon' => 'truck', 'title' => 'Fast & Reliable Shipping', 'text' => 'Complimentary express delivery over ₹15,000, tracked from our door to yours.'],
                ['icon' => 'rotate', 'title' => 'Easy Returns', 'text' => '30 days to return or exchange, collected from your home at no charge.'],
            ],

            'newsletter_eyebrow' => 'Newsletter',
            'newsletter_heading' => 'Enter the world of *Maison Élan*',
            'newsletter_text' => 'Early access to new collections, private sales and stories from the atelier — a few times a season, never more.',
        ]);
    }
}
