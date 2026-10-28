<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use App\Support\Media;
use Illuminate\Database\Seeder;

/**
 * Pages and FAQs for the Bulk Order template. Everything is tagged with the bulk
 * template, so the other storefronts' content is untouched and every template serves
 * /about, /faq, /privacy… at the same URLs.
 */
class BulkContentSeeder extends Seeder
{
    public function run(): void
    {
        $img = fn (string $id, int $w = 1600, int $h = 900) => Media::unsplash($id, $w, "&h={$h}");

        $pages = [
            'about' => [
                'title' => 'Wholesale supplies, ordered in one go.',
                'eyebrow' => 'About us',
                'template' => 'about',
                'excerpt' => 'StockUp supplies shops, offices, cafés and kitchens with the everyday products they reorder every week — in the pack sizes they actually buy.',
                'image' => $img('1586201375761-83865001e31c', 2000, 900),
                'meta_title' => 'About StockUp — wholesale supplies, ordered in one go',
                'meta_description' => 'Who we are and how our one-screen bulk order form saves businesses time on every restock.',
                'data' => [
                    'sections' => [
                        ['layout' => 'text-image', 'eyebrow' => 'Ordering', 'heading' => 'Your whole list on one screen', 'body' => '<p>Businesses restock the same products again and again. Our bulk order form lets you search, set quantities and add every line to the cart in one pass — no clicking through fifty product pages.</p>', 'image' => $img('1544787219-7f47ccb76574', 1200, 900)],
                        ['layout' => 'image-text', 'eyebrow' => 'Pack sizes', 'heading' => 'Bags, cartons and cases', 'body' => '<p>Every product comes in the sizes businesses buy: 25 kg bags, cartons of 48, cases of 24. Pick the size on the same line as the quantity.</p>', 'image' => $img('1558961363-fa8fdf82db35', 1200, 900)],
                    ],
                    'values' => [
                        ['title' => 'Fast ordering', 'text' => 'Restock in minutes, not an afternoon.'],
                        ['title' => 'Honest pricing', 'text' => 'The price you see is the price you pay.'],
                        ['title' => 'GST invoices', 'text' => 'Input credit on every order.'],
                        ['title' => 'On-time dispatch', 'text' => 'Orders leave within 24 hours.'],
                    ],
                ],
            ],
            'contact' => [
                'title' => 'Talk to our order team',
                'eyebrow' => 'Contact',
                'template' => 'contact',
                'excerpt' => 'Order help, a quote for large quantities or a recurring supply — call, WhatsApp or write to us. We reply within a few hours on working days.',
                'meta_description' => 'Contact StockUp by phone, WhatsApp, email or the form below. Quotes for large quantities welcome.',
                'data' => ['subjects' => ['Quote', 'Order issue', 'Recurring supply', 'Return or replacement', 'GST invoice', 'Other']],
            ],
            'faq' => [
                'title' => 'Frequently asked questions',
                'eyebrow' => 'Help',
                'template' => 'faq',
                'excerpt' => 'Bulk ordering, delivery, GST invoices and returns — answered.',
            ],
            'shipping' => [
                'title' => 'Shipping & delivery',
                'template' => 'legal',
                'excerpt' => 'How long it takes and what it costs.',
                'body' => '<h3>Timelines</h3><p>Orders dispatch within 24 hours on working days and arrive in 2–5 days depending on your pincode.</p><h3>Charges</h3><p>Free delivery above the threshold shown at checkout; otherwise a charge based on weight and pincode, shown before you pay.</p>',
            ],
            'returns' => [
                'title' => 'Returns & replacements',
                'template' => 'legal',
                'excerpt' => 'Seven-day returns on unopened products.',
                'body' => '<h3>Eligibility</h3><p>Unopened products in original packing can be returned within 7 days of delivery. Food items with a broken seal cannot be returned.</p><h3>Damaged or wrong item</h3><p>Report it from your order page within 48 hours with photos; we replace it at no cost.</p>',
            ],
            'refund-policy' => [
                'title' => 'Refund policy',
                'template' => 'legal',
                'body' => '<h3>When refunds apply</h3><p>Cancelled orders before dispatch, returned products that pass inspection, and items we could not fulfil.</p><h3>Timelines</h3><p>Prepaid: 5–7 working days to the original method. COD: bank transfer within 7 working days of pickup.</p>',
            ],
            'privacy' => [
                'title' => 'Privacy policy',
                'template' => 'legal',
                'body' => '<h3>What we collect</h3><p>Your name, phone, email, delivery addresses, GST details if provided, and order history.</p><h3>How we use it</h3><p>To fulfil and deliver orders, issue GST invoices, provide support and — with your consent — send offers.</p><h3>Sharing</h3><p>Only with delivery partners and payment gateways as needed to complete your order. We never sell your data.</p>',
            ],
            'terms' => [
                'title' => 'Terms & conditions',
                'template' => 'legal',
                'body' => '<h3>Orders</h3><p>An order is confirmed when you receive the confirmation screen and email. Prices include GST unless stated; availability can change and we will refund any item we cannot supply.</p><h3>Quantities</h3><p>Some products have a per-order limit shown on the order form; contact us for larger quantities.</p>',
            ],
            'cookies' => [
                'title' => 'Cookie policy',
                'template' => 'cookies',
                'excerpt' => 'What cookies we use and how to control them.',
                'body' => '<h3>Essential</h3><p>Keep your cart and login session working.</p><h3>Analytics</h3><p>Help us understand which products people look for.</p><h3>Marketing</h3><p>Show relevant offers on other sites. Off unless you accept.</p>',
            ],
        ];

        foreach ($pages as $slug => $attrs) {
            Page::updateOrCreate(['slug' => $slug, 'storefront_template' => 'bulk'], $attrs + ['is_active' => true, 'body' => $attrs['body'] ?? null]);
        }

        $faqs = [
            ['Ordering', 'How does the bulk order form work?', '<p>Click a search box and pick a product (or type its name or code), choose the pack size and quantity, then add another line. When your list is ready, “Add to cart” sends every line to the cart at once.</p>'],
            ['Ordering', 'Can I still order from product pages?', '<p>Yes. Every product has its own page with an add-to-cart button; the bulk form is simply faster for long lists.</p>'],
            ['Ordering', 'Why can’t I raise the quantity on some lines?', '<p>A few products have a per-order limit, shown under the quantity. Contact us for a quote if you need more.</p>'],
            ['Ordering', 'Can I get a GST invoice?', '<p>Yes. Add your GSTIN in the order notes or on your account and the invoice is issued in your business name.</p>'],
            ['Delivery', 'How long does delivery take?', '<p>Dispatch within 24 hours on working days; delivery in 2–5 days depending on your pincode.</p>'],
            ['Payments', 'Which payment methods do you accept?', '<p>UPI, credit and debit cards, net banking, wallets and cash on delivery.</p>'],
            ['Returns', 'What can be returned?', '<p>Unopened products in original packing within 7 days of delivery.</p>'],
        ];
        Faq::where('template', 'bulk')->delete();
        foreach ($faqs as $i => [$cat, $q, $a]) {
            Faq::create(['category' => $cat, 'question' => $q, 'answer' => $a, 'sort_order' => $i, 'is_active' => true, 'template' => 'bulk']);
        }
    }
}
