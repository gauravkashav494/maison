<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Support\Media;
use Illuminate\Database\Seeder;

/**
 * Pages, FAQs and recipe posts for the Indian Grocery template. Everything is
 * tagged with the grocery template, so the Fashion content is untouched and both
 * templates can serve /about, /faq, /privacy… at the same URLs.
 */
class GroceryContentSeeder extends Seeder
{
    public function run(): void
    {
        $img = fn (string $id, int $w = 1600, int $h = 900) => Media::unsplash($id, $w, "&h={$h}");

        $pages = [
            'about' => [
                'title' => 'Fresh, fast and fairly priced.',
                'eyebrow' => 'About us',
                'template' => 'about',
                'excerpt' => 'We started with one dark store and a promise: groceries as fresh as the market, delivered faster than you can find your car keys.',
                'image' => $img('1542838132-92c53300491e', 2000, 900),
                'meta_title' => 'About us — fresh groceries delivered in minutes',
                'meta_description' => 'Who we are, how our dark stores work, and why our produce is fresher than the supermarket.',
                'data' => [
                    'sections' => [
                        ['layout' => 'text-image', 'eyebrow' => 'How it works', 'heading' => 'Neighbourhood dark stores, not warehouses', 'body' => '<p>Every order is packed at a small store within a few kilometres of your home. That is how we deliver in 10–30 minutes without a single item spending days in transit.</p>', 'image' => $img('1604719312566-8912e9227c6a', 1200, 900)],
                        ['layout' => 'image-text', 'eyebrow' => 'Freshness', 'heading' => 'From the farm at 4 am, at your door by breakfast', 'body' => '<p>Fruits and vegetables come straight from partner farmers and mandis every morning. Anything that does not pass our quality check goes to our food-bank partners, never to your basket.</p>', 'image' => $img('1488459716781-31db52582fe9', 1200, 900)],
                        ['layout' => 'text-image', 'eyebrow' => 'Pricing', 'heading' => 'Everyday low prices, no membership fees', 'body' => '<p>We buy in bulk, waste less and pass the savings on. Compare us with your local kirana — and tell us if we are ever more expensive.</p>', 'image' => $img('1583258292688-d0213dc5a3a8', 1200, 900)],
                    ],
                    'values' => [
                        ['title' => 'Fresh guarantee', 'text' => 'Not fresh? Report it within 24 hours for a replacement or refund.'],
                        ['title' => 'Honest weights', 'text' => 'Every pack is weighed at the store — you get what you pay for.'],
                        ['title' => 'Fair to partners', 'text' => 'Delivery partners are paid per hour, not per rush.'],
                        ['title' => 'Less waste', 'text' => 'Reusable crates, paper bags and same-day donations of surplus food.'],
                    ],
                    'sustainability_heading' => 'Greener groceries, one delivery at a time.',
                    'sustainability_body' => '<p>Electric two-wheelers on 70% of routes, no single-use plastic bags, and a zero-food-waste target for every dark store by next year.</p>',
                    'sustainability_image' => $img('1573246123716-6b1782bfc499', 1200, 900),
                    'founder_quote' => 'We wanted the corner shop feeling — someone who knows what you need — with the convenience of an app.',
                    'founder_name' => 'Rohit Sharma, founder',
                ],
            ],
            'contact' => [
                'title' => 'How can we help?',
                'eyebrow' => 'Contact',
                'template' => 'contact',
                'excerpt' => 'Order issues, missing items or refunds — we reply within a few hours, every day of the week.',
                'meta_description' => 'Contact customer support by phone, WhatsApp, email or the form below.',
                'data' => ['subjects' => ['Order issue', 'Missing or damaged item', 'Refund', 'Product question', 'Delivery area request', 'Other']],
            ],
            'faq' => [
                'title' => 'Frequently asked questions',
                'eyebrow' => 'Help',
                'template' => 'faq',
                'excerpt' => 'Delivery times, freshness, payments, returns and your account — answered.',
            ],
            'shipping' => [
                'title' => 'Delivery areas & timings',
                'template' => 'legal',
                'excerpt' => 'Where we deliver, how fast, and what it costs.',
                'body' => '<h3>Delivery areas</h3><p>We currently deliver in Bengaluru, Mumbai, Delhi NCR, Hyderabad, Chennai, Pune, Kolkata and Ahmedabad. Enter your pincode in the header to check your locality.</p><h3>Timings</h3><p>Orders are delivered from 7 am to 11 pm, all days. Most orders arrive within 10–30 minutes; during heavy rain or peak hours it may take up to 60 minutes.</p><h3>Charges</h3><p>Delivery charges depend on your order value and are shown at checkout before you pay. Orders above the free-delivery threshold are delivered free.</p><h3>Scheduled delivery</h3><p>Prefer a slot? Choose a delivery option at checkout and we will bring your order in that window.</p>',
            ],
            'returns' => [
                'title' => 'Returns & refunds',
                'template' => 'legal',
                'excerpt' => 'Our fresh guarantee and how refunds work.',
                'body' => '<h3>Fresh guarantee</h3><p>If any item is stale, damaged, expired or not what you ordered, report it from your order page within 24 hours of delivery. We will replace it or refund it — no questions asked.</p><h3>What can be returned</h3><p>Packaged goods in original condition can be returned at the door or reported within 24 hours. For hygiene reasons, opened dairy, meat, frozen items and personal-care products cannot be returned unless defective.</p><h3>Refunds</h3><p>Refunds go back to the original payment method within 3–5 business days. Cash-on-delivery refunds are credited to your wallet or bank account.</p><h3>Missing items</h3><p>If something on your bill is missing from the bag, report it and we will refund it immediately.</p>',
            ],
            'privacy' => [
                'title' => 'Privacy policy',
                'template' => 'legal',
                'body' => '<h3>What we collect</h3><p>Your name, phone number, email, delivery addresses, order history and — with permission — your device location to show delivery availability.</p><h3>How we use it</h3><p>To deliver your orders, contact you about them, personalise offers and improve the service. We never sell your data.</p><h3>Sharing</h3><p>Delivery partners see only your name, address and phone number for the order they carry. Payment details are processed by PCI-DSS certified gateways and never stored by us.</p><h3>Your rights</h3><p>You can view, correct or delete your data from your account or by contacting support. We keep order records for as long as the law requires.</p>',
            ],
            'terms' => [
                'title' => 'Terms & conditions',
                'template' => 'legal',
                'body' => '<h3>Orders</h3><p>An order is confirmed when you receive the confirmation screen and email. Prices and availability can change; we will refund any item we cannot fulfil.</p><h3>Quantities</h3><p>Some items have a maximum quantity per order to keep stock available for every household.</p><h3>Delivery</h3><p>Someone must be available to receive the order at the address given. If nobody answers, our partner will try to contact you before returning the order.</p><h3>Cancellations</h3><p>Orders can be cancelled until they are packed. After that, use the fresh guarantee for any issue with the delivered items.</p><h3>Account</h3><p>You are responsible for keeping your login details safe. We may suspend accounts that abuse offers or refund policies.</p>',
            ],
            'cookies' => [
                'title' => 'Cookie policy',
                'template' => 'cookies',
                'excerpt' => 'What cookies we use and how to control them.',
                'body' => '<h3>Essential</h3><p>Keep your cart, login session and delivery location. The site cannot work without them.</p><h3>Analytics</h3><p>Help us understand which products and pages are useful. Anonymous and optional.</p><h3>Marketing</h3><p>Show you relevant offers. Optional — control them from the panel on this page.</p>',
            ],
            'payment' => [
                'title' => 'Payment options',
                'template' => 'legal',
                'excerpt' => 'Accepted methods and security.',
                'body' => '<h3>Accepted methods</h3><ul><li>UPI — Google Pay, PhonePe, Paytm and every UPI app</li><li>Credit and debit cards — Visa, Mastercard, RuPay</li><li>Wallets — Paytm, Amazon Pay</li><li>Net banking</li><li>Cash on delivery (pay by cash or UPI at the door)</li></ul><h3>Security</h3><p>Payments are processed by PCI-DSS Level 1 certified partners over encrypted connections. We never see or store your card number.</p><h3>Pricing</h3><p>All prices are in Indian rupees and include GST. The bill you see at checkout is the bill you pay.</p>',
            ],
            'gift-cards' => [
                'title' => 'Gift groceries, not guesses.',
                'eyebrow' => 'Gift cards',
                'template' => 'gift-cards',
                'excerpt' => 'Send a grocery gift card to family, friends or house-help — delivered by email in minutes, valid for a year.',
                'image' => $img('1608686207856-001b95cf60ca', 1600, 800),
                'data' => ['steps' => [
                    ['title' => 'Pick an amount', 'text' => 'From ₹250 to ₹5,000.'],
                    ['title' => 'Add a message', 'text' => 'We email the card with your note.'],
                    ['title' => 'They shop', 'text' => 'Redeemable on every product at checkout.'],
                ]],
            ],
            'careers' => [
                'title' => 'Join the team.',
                'eyebrow' => 'Careers',
                'template' => 'careers',
                'excerpt' => 'Store partners, riders, category managers and engineers — we are hiring across every city we deliver in.',
                'image' => $img('1556910103-1c02745aae4d', 1600, 800),
                'data' => ['roles' => [
                    ['title' => 'Dark Store Manager', 'location' => 'Bengaluru', 'type' => 'Full-time', 'summary' => 'Run a neighbourhood store: stock, freshness checks, packing speed and a small team of partners.'],
                    ['title' => 'Delivery Partner', 'location' => 'All cities', 'type' => 'Flexible', 'summary' => 'Deliver within a few kilometres on an e-scooter. Hourly pay, fuel included, weekly payouts.'],
                    ['title' => 'Category Manager — Fresh', 'location' => 'Mumbai', 'type' => 'Full-time', 'summary' => 'Source fruits and vegetables from farmers and mandis; own quality, pricing and waste.'],
                    ['title' => 'Backend Engineer', 'location' => 'Remote (India)', 'type' => 'Full-time', 'summary' => 'Build the ordering, routing and inventory systems behind 10-minute delivery.'],
                ], 'perks' => ['Free groceries allowance every month', 'Health insurance for you and family', 'Hourly pay for partners, never per-order pressure', 'Fast growth into store and city roles']],
            ],
            'stores' => [
                'title' => 'Our stores',
                'eyebrow' => 'Locations',
                'template' => 'stores',
                'excerpt' => 'Walk in, pick up an order, or return an item at any of our neighbourhood stores.',
            ],
        ];

        foreach ($pages as $slug => $attrs) {
            Page::updateOrCreate(['slug' => $slug, 'storefront_template' => 'grocery'], $attrs + ['is_active' => true, 'body' => $attrs['body'] ?? null]);
        }

        $faqs = [
            ['Orders', 'How fast will my order arrive?', '<p>Most orders arrive within 10–30 minutes of being placed. You can watch the live status on your order page.</p>'],
            ['Orders', 'Is there a minimum order value?', '<p>No minimum. Small orders may carry a delivery charge, which is shown at checkout before you pay.</p>'],
            ['Orders', 'Can I change or cancel an order?', '<p>Yes — until it is packed. Open the order from your account and tap Cancel. After packing, use the fresh guarantee for any issue.</p>'],
            ['Shipping', 'Which areas do you deliver to?', '<p>Bengaluru, Mumbai, Delhi NCR, Hyderabad, Chennai, Pune, Kolkata and Ahmedabad. Enter your pincode in the header to check your locality.</p>'],
            ['Shipping', 'What are your delivery hours?', '<p>7 am to 11 pm, all days including holidays.</p>'],
            ['Shipping', 'Can I schedule a delivery?', '<p>Yes. Choose a delivery option at checkout and we will deliver in that window.</p>'],
            ['Returns', 'What if an item is not fresh?', '<p>Report it from your order page within 24 hours and we will replace or refund it — no questions asked.</p>'],
            ['Returns', 'How long do refunds take?', '<p>3–5 business days to the original payment method. Cash-on-delivery refunds go to your wallet or bank account.</p>'],
            ['Payments', 'Which payment methods do you accept?', '<p>UPI, credit and debit cards, wallets, net banking and cash on delivery.</p>'],
            ['Payments', 'Are prices inclusive of GST?', '<p>Yes. The bill you see at checkout is what you pay — no surprises at the door.</p>'],
            ['Products', 'What do the green and red marks mean?', '<p>A green mark means the product is vegetarian; a red mark means it contains egg, meat or fish.</p>'],
            ['Products', 'How do you keep produce fresh?', '<p>Fruits and vegetables arrive from farms and mandis every morning and are stored chilled at the dark store until they are packed for you.</p>'],
            ['Account', 'Do I need an account to order?', '<p>No, you can check out as a guest. An account lets you save addresses, reorder in one tap and track every order.</p>'],
            ['General', 'Do you offer a subscription for milk or bread?', '<p>Not yet — one-tap reorders from your account are the quickest way to restock daily essentials.</p>'],
        ];
        Faq::where('template', 'grocery')->delete();
        foreach ($faqs as $i => [$cat, $q, $a]) {
            Faq::create(['category' => $cat, 'question' => $q, 'answer' => $a, 'sort_order' => $i, 'is_active' => true, 'template' => 'grocery']);
        }

        $posts = [
            ['slug' => '15-minute-paneer-bhurji', 'title' => '15-minute paneer bhurji', 'category' => 'Recipes', 'excerpt' => 'A weeknight classic: crumbled paneer, onions, tomatoes and a handful of masalas. Ready before the rotis are.', 'image' => $img('1631452180519-c014fe946bc7', 1400, 900), 'read_time' => 3, 'is_featured' => true, 'body' => '<h2>Ingredients</h2><ul><li>200 g paneer, crumbled</li><li>1 onion, 2 tomatoes, finely chopped</li><li>1 green chilli, ½ tsp turmeric, 1 tsp garam masala</li><li>Coriander, salt, 1 tbsp oil</li></ul><h2>Method</h2><ol><li>Heat oil, soften the onion and chilli.</li><li>Add tomatoes and spices; cook until the oil separates.</li><li>Fold in the paneer, cook 3 minutes, finish with coriander.</li></ol>'],
            ['slug' => 'how-to-store-fruits-and-vegetables', 'title' => 'How to keep fruits and vegetables fresh for longer', 'category' => 'Tips', 'excerpt' => 'Which ones go in the fridge, which stay out, and why bananas should live alone.', 'image' => $img('1610348725531-843dff563e2c', 1400, 900), 'read_time' => 4, 'body' => '<p>Leafy greens: wash, dry, wrap in a kitchen towel and refrigerate. Tomatoes and bananas: room temperature, away from other fruit — they release ethylene that ripens everything nearby. Potatoes and onions: cool, dark and separate. Berries: unwashed in the fridge; rinse just before eating.</p>'],
            ['slug' => 'weekly-meal-prep-under-999', 'title' => 'A week of meals under ₹999', 'category' => 'Budget', 'excerpt' => 'Dal, rice, two sabzis, eggs and fruit — a realistic plan for two people, with the shopping list.', 'image' => $img('1547592180-85f173990554', 1400, 900), 'read_time' => 5, 'body' => '<h2>The list</h2><ul><li>Toor dal 1 kg, basmati rice 2 kg, atta 2 kg</li><li>Onions, tomatoes, potatoes, spinach, carrots</li><li>12 eggs, 1 L milk, 200 g paneer</li><li>Bananas and apples for breakfast</li></ul><p>Cook dal and rice in bulk on Sunday; rotate sabzis through the week; keep eggs for quick dinners.</p>'],
        ];
        foreach ($posts as $post) {
            Post::updateOrCreate(['slug' => $post['slug'], 'template' => 'grocery'], $post + ['published_at' => now()->subDays(rand(1, 20))]);
        }
    }
}
