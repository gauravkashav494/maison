<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pages the Bulk Order template links to from its menus, footer and cookie banner. Only creates
 * what is missing, so a page the client has already edited is left alone when this runs again.
 */
class BulkPagesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $attrs) {
            Page::firstOrCreate(['slug' => $slug, 'storefront_template' => 'bulk'], $attrs + ['is_active' => true]);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function pages(): array
    {
        return [
            'contact' => [
                'title' => 'Contact us',
                'template' => 'contact',
                'excerpt' => 'Opening a trade account, a large order or a delivery question? Send us a message and the trade desk will get back to you.',
                'meta_description' => 'Get in touch about an order, a price or product availability.',
                'data' => ['subjects' => ['Order enquiry', 'Product question', 'Trade account or pricing', 'Returns', 'Other']],
            ],
            'privacy' => [
                'title' => 'Privacy policy',
                'template' => 'legal',
                'excerpt' => 'What we collect when you order, why, and who it is shared with.',
                'body' => '<h3>What we collect</h3><p>Your name, business name, phone, email, delivery addresses, GST details if provided, and your order history.</p><h3>How we use it</h3><p>To process and deliver orders, issue GST invoices, keep your saved lists and past orders ready to reorder, answer your questions and — only with your consent — send offers.</p><h3>Sharing</h3><p>Only with delivery partners and payment providers, as needed to complete your order. We never sell your data.</p><h3>Your choices</h3><p>You can update your details from your account, ask for a copy of what we hold, or ask us to delete it, by contacting us.</p>',
            ],
            'terms' => [
                'title' => 'Terms & conditions',
                'template' => 'legal',
                'excerpt' => 'The terms that apply when you place an order with us.',
                'body' => '<h3>Orders</h3><p>An order is confirmed when you see the confirmation screen and receive the confirmation email. Quantities may be limited per item.</p><h3>Prices and GST</h3><p>Prices are shown before delivery and GST, which are added at checkout. Prices and stock are checked when you order; if an item cannot be supplied we will tell you and refund it.</p><h3>Delivery</h3><p>Delivery times are estimates. Please check the goods on arrival and tell us about any shortage or damage within 48 hours.</p><h3>Trade accounts</h3><p>Account pricing and credit terms, where offered, are agreed separately and may be reviewed at any time.</p>',
            ],
            'refund-policy' => [
                'title' => 'Refund policy',
                'template' => 'legal',
                'excerpt' => 'When refunds apply and how long they take.',
                'body' => '<h3>When refunds apply</h3><p>Orders cancelled before dispatch, returned items that pass inspection, and items we could not supply.</p><h3>Returns</h3><p>Unopened items in their original packaging can be returned within 7 days of delivery. Contact us first so we can arrange collection.</p><h3>Timelines</h3><p>Prepaid orders are refunded to the original payment method within 5–7 working days of approval. Cash on delivery orders are refunded by bank transfer.</p><h3>Part returns</h3><p>If part of an order is returned, the refund covers the returned quantity only, and any quantity discount is recalculated on what you keep.</p>',
            ],
            'cookies' => [
                'title' => 'Cookie policy',
                'template' => 'cookies',
                'excerpt' => 'What cookies we use and how to change your choice.',
                'body' => '<h3>Essential</h3><p>Keep your order, sign-in and saved lists working. These are always on.</p><h3>Analytics</h3><p>Anonymous statistics that show us which products and pages people use, so we can improve the store. Off unless you agree.</p><h3>Marketing</h3><p>Used to show relevant offers on other sites. Off unless you agree.</p><h3>Changing your choice</h3><p>Use the panel on this page at any time. Clearing your browser cookies also resets it.</p>',
            ],
        ];
    }
}
