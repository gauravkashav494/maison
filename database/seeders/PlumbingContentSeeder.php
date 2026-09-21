<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Support\Media;
use Illuminate\Database\Seeder;

/**
 * Pages, FAQs and blog posts for the Plumbing template. Everything is tagged with
 * the plumbing template, so the other storefronts' content is untouched and every
 * template serves /about, /faq, /privacy… at the same URLs.
 */
class PlumbingContentSeeder extends Seeder
{
    public function run(): void
    {
        $img = fn (string $id, int $w = 1600, int $h = 900) => Media::unsplash($id, $w, "&h={$h}");

        $pages = [
            'about' => [
                'title' => 'Genuine plumbing supplies, delivered pan-India.',
                'eyebrow' => 'About us',
                'template' => 'about',
                'excerpt' => 'PlumbKart started in a Delhi hardware market with one idea: plumbers, builders and homeowners deserve genuine products at trade prices, without the running around.',
                'image' => $img('1778090533461-c5c2f29637a8', 2000, 900),
                'meta_title' => 'About PlumbKart — India’s online plumbing & sanitaryware store',
                'meta_description' => 'Who we are, how we source directly from manufacturers, and why contractors across India trust us for pipes, fittings and sanitaryware.',
                'data' => [
                    'sections' => [
                        ['layout' => 'text-image', 'eyebrow' => 'Sourcing', 'heading' => 'Direct from manufacturers and authorised distributors', 'body' => '<p>Every pipe, fitting, tap and pump on PlumbKart comes from the brand or its authorised distributor — never grey-market stock. That is how we can pass on the manufacturer warranty on every order.</p>', 'image' => $img('1699322039731-fdc996a9bb1c', 1200, 900)],
                        ['layout' => 'image-text', 'eyebrow' => 'Logistics', 'heading' => 'From our warehouses to 18,000+ pincodes', 'body' => '<p>Three regional warehouses in Delhi NCR, Pune and Hyderabad dispatch within 24 hours. Long pipes, tanks and sanitaryware travel on dedicated surface transport, packed for site conditions.</p>', 'image' => $img('1545193329-4a052e14eb8f', 1200, 900)],
                        ['layout' => 'text-image', 'eyebrow' => 'Professionals', 'heading' => 'Built for people who buy by the box', 'body' => '<p>Contractor packs, volume pricing, GST invoices and scheduled site deliveries — because half our customers are plumbers and builders who need the right part on the right day.</p>', 'image' => $img('1676210134188-4c05dd172f89', 1200, 900)],
                    ],
                    'values' => [
                        ['title' => 'Genuine only', 'text' => 'Authorised brands with manufacturer warranty, every time.'],
                        ['title' => 'Trade pricing', 'text' => 'Honest prices for homeowners, better prices for volume.'],
                        ['title' => 'Expert help', 'text' => 'Our support team are plumbers, not scripts.'],
                        ['title' => 'On-time delivery', 'text' => 'Dispatch in 24 hours, tracked to your door or site.'],
                    ],
                    'sustainability_heading' => 'Less waste on every site.',
                    'sustainability_body' => '<p>Returnable pipe bundles, recycled packaging and lead-free CPVC/UPVC ranges across the catalogue.</p>',
                    'sustainability_image' => $img('1784972857429-3707b41367dd', 1200, 900),
                    'founder_quote' => 'A plumber should never lose a day’s work waiting for a fitting. That is the whole company.',
                    'founder_name' => 'Rohit Sharma, founder',
                ],
            ],
            'contact' => [
                'title' => 'Talk to a plumbing expert',
                'eyebrow' => 'Contact',
                'template' => 'contact',
                'excerpt' => 'Product questions, order help or a bulk requirement — call, WhatsApp or write to us. We reply within a few hours on working days.',
                'meta_description' => 'Contact PlumbKart by phone, WhatsApp, email or the form below. Bulk quote requests welcome.',
                'data' => ['subjects' => ['Bulk quote', 'Product question', 'Order issue', 'Return or replacement', 'GST invoice', 'Dealer enquiry', 'Other']],
            ],
            'faq' => [
                'title' => 'Frequently asked questions',
                'eyebrow' => 'Help',
                'template' => 'faq',
                'excerpt' => 'Delivery, GST invoices, returns, bulk orders and product selection — answered.',
            ],
            'help-center' => [
                'title' => 'Help center',
                'template' => 'legal',
                'excerpt' => 'Guides and answers for ordering, delivery and installation.',
                'body' => '<h3>Ordering</h3><p>Add products to your cart, choose a size where offered and check out with UPI, cards, net banking, wallets or cash on delivery. Guest checkout is available; an account lets you track orders and reorder.</p><h3>Delivery</h3><p>Orders dispatch within 24 hours on working days. Small items arrive in 2–4 days, pipes, tanks and sanitaryware in 4–6 days by surface transport. Track every order from your account or the Track Order page.</p><h3>Installation</h3><p>Every product page has installation notes and specifications. For anything beyond that, call or WhatsApp our support team — they are practising plumbers.</p><h3>Bulk orders</h3><p>Use the Bulk Quote form or WhatsApp us the list; a project executive will reply with pricing within one working day.</p>',
            ],
            'shipping' => [
                'title' => 'Shipping & delivery',
                'template' => 'legal',
                'excerpt' => 'Where we deliver, how long it takes and what it costs.',
                'body' => '<h3>Coverage</h3><p>We deliver to 18,000+ pincodes across India. Enter your pincode at checkout to see the estimate for your address.</p><h3>Timelines</h3><p>Dispatch within 24 hours on working days. Fittings, taps, valves and tools: 2–4 days. Pipes, tanks, pumps and sanitaryware: 4–6 days by surface transport.</p><h3>Charges</h3><p>Free delivery on orders above the threshold shown at checkout; otherwise a flat charge based on weight and pincode. Long pipes (3 m / 6 m) and tanks carry a freight charge that is shown before you pay.</p><h3>Site delivery</h3><p>For project orders we can schedule delivery to a construction site with a contact person on the ground. Mention it in the order notes.</p>',
            ],
            'returns' => [
                'title' => 'Returns & replacements',
                'template' => 'legal',
                'excerpt' => 'Seven-day returns on unused products in original packing.',
                'body' => '<h3>Eligibility</h3><p>Unused products in original, undamaged packing can be returned within 7 days of delivery. Cut pipes, opened solvent cement and installed sanitaryware cannot be returned.</p><h3>Damaged or wrong item</h3><p>Report it from your order page within 48 hours with photos; we replace it at no cost.</p><h3>Refunds</h3><p>Refunds go to the original payment method within 5–7 working days after the pickup is inspected. COD refunds are transferred to your bank account.</p>',
            ],
            'refund-policy' => [
                'title' => 'Refund policy',
                'template' => 'legal',
                'body' => '<h3>When refunds apply</h3><p>Cancelled orders before dispatch, returned products that pass inspection, and items we could not fulfil.</p><h3>Timelines</h3><p>Prepaid: 5–7 working days to the original method. COD: bank transfer within 7 working days of pickup.</p><h3>Partial refunds</h3><p>If part of a bulk order is returned, the refund is for the returned quantity only; volume discounts are recalculated on the retained quantity.</p>',
            ],
            'privacy' => [
                'title' => 'Privacy policy',
                'template' => 'legal',
                'body' => '<h3>What we collect</h3><p>Your name, phone, email, delivery addresses, GST details if provided, and order history.</p><h3>How we use it</h3><p>To fulfil and deliver orders, issue GST invoices, provide support and — with your consent — send offers.</p><h3>Sharing</h3><p>Only with delivery partners and payment gateways as needed to complete your order. We never sell your data.</p>',
            ],
            'terms' => [
                'title' => 'Terms & conditions',
                'template' => 'legal',
                'body' => '<h3>Orders</h3><p>An order is confirmed when you receive the confirmation screen and email. Prices include GST unless stated; availability can change and we will refund any item we cannot supply.</p><h3>Warranty</h3><p>Manufacturer warranties apply as stated on each product page. Warranty claims are routed to the brand’s service network.</p><h3>Use of products</h3><p>Products must be installed as per the manufacturer’s instructions and applicable codes. Installation notes on PlumbKart are guidance only.</p>',
            ],
            'cookies' => [
                'title' => 'Cookie policy',
                'template' => 'cookies',
                'excerpt' => 'What cookies we use and how to control them.',
                'body' => '<h3>Essential</h3><p>Keep your cart and login session working.</p><h3>Analytics</h3><p>Help us understand which products and categories people look for.</p><h3>Marketing</h3><p>Show relevant offers on other sites. Off unless you accept.</p>',
            ],
            'payment' => [
                'title' => 'Payment options',
                'template' => 'legal',
                'excerpt' => 'UPI, cards, net banking, wallets and cash on delivery.',
                'body' => '<h3>Accepted methods</h3><ul><li>UPI — Google Pay, PhonePe, Paytm and every UPI app</li><li>Credit and debit cards — Visa, Mastercard, RuPay</li><li>Net banking</li><li>Wallets</li><li>Cash on delivery (up to the limit shown at checkout)</li></ul><h3>GST invoice</h3><p>Add your GSTIN in the order notes or your account to receive a GST invoice for input credit.</p>',
            ],
            'gift-cards' => [
                'title' => 'Gift cards for the site.',
                'eyebrow' => 'Gift cards',
                'template' => 'gift-cards',
                'excerpt' => 'A PlumbKart gift card lets a plumber, contractor or new homeowner pick exactly the fittings they need.',
                'image' => $img('1503789146722-cf137a3c0fea', 1600, 800),
                'data' => ['steps' => [
                    ['title' => 'Pick an amount', 'text' => 'From ₹500 to ₹25,000.'],
                    ['title' => 'Add a message', 'text' => 'We email the card with your note.'],
                    ['title' => 'They shop', 'text' => 'Redeemable on every product at checkout.'],
                ]],
            ],
            'careers' => [
                'title' => 'Work with us.',
                'eyebrow' => 'Careers',
                'template' => 'careers',
                'excerpt' => 'Warehouse, category, support and engineering roles — across Delhi NCR, Pune and Hyderabad.',
                'image' => $img('1632201147654-f6f54427e538', 1600, 800),
                'data' => ['roles' => [
                    ['title' => 'Category Manager — Sanitaryware', 'location' => 'Delhi NCR', 'type' => 'Full-time', 'summary' => 'Own the sanitaryware and bathroom fittings range: brands, pricing and new launches.'],
                    ['title' => 'Plumbing Support Executive', 'location' => 'Remote (India)', 'type' => 'Full-time', 'summary' => 'Help customers choose the right product and solve installation questions on phone and WhatsApp.'],
                    ['title' => 'Warehouse Supervisor', 'location' => 'Pune', 'type' => 'Full-time', 'summary' => 'Run inbound, storage and 24-hour dispatch for pipes, tanks and fittings.'],
                    ['title' => 'Backend Engineer', 'location' => 'Remote (India)', 'type' => 'Full-time', 'summary' => 'Build the ordering, pricing and logistics systems behind pan-India delivery.'],
                ], 'perks' => ['Health insurance for you and your family', 'Learning budget and brand training', 'Performance bonus every quarter', 'Fast growth into regional roles']],
            ],
            'stores' => [
                'title' => 'Our stores & warehouses',
                'eyebrow' => 'Locations',
                'template' => 'stores',
                'excerpt' => 'Walk in for counter sales, pick up an online order or return an item.',
            ],
        ];

        foreach ($pages as $slug => $attrs) {
            Page::updateOrCreate(['slug' => $slug, 'storefront_template' => 'plumbing'], $attrs + ['is_active' => true, 'body' => $attrs['body'] ?? null]);
        }

        $faqs = [
            ['Orders', 'Do you sell to individuals or only to contractors?', '<p>Both. Homeowners get the same genuine products; contractors and builders get volume pricing through the bulk quote form.</p>'],
            ['Orders', 'Can I get a GST invoice?', '<p>Yes. Add your GSTIN in the order notes or on your account and the invoice is issued in your business name for input credit.</p>'],
            ['Orders', 'Is there a minimum order value?', '<p>No minimum. Orders below the free-delivery threshold carry a small delivery charge shown at checkout.</p>'],
            ['Shipping', 'How long does delivery take?', '<p>Dispatch within 24 hours on working days. Fittings, taps and tools arrive in 2–4 days; pipes, tanks, pumps and sanitaryware in 4–6 days by surface transport.</p>'],
            ['Shipping', 'Do you deliver 3 m and 6 m pipes?', '<p>Yes. Long pipes ship in bundles on dedicated transport. A freight charge based on your pincode is shown before payment.</p>'],
            ['Shipping', 'Can you deliver to a construction site?', '<p>Yes — mention the site address and an on-site contact in the order notes and we will schedule the delivery.</p>'],
            ['Products', 'Are the products genuine?', '<p>Every product comes from the brand or its authorised distributor and carries the manufacturer warranty.</p>'],
            ['Products', 'How do I choose between PVC, CPVC and UPVC?', '<p>CPVC for hot & cold water lines, UPVC for cold water supply, PVC for drainage, agriculture and low-pressure lines. Every product page lists the applications.</p>'],
            ['Products', 'Which pipe size do I need?', '<p>Half-inch for individual taps, three-quarter inch for bathroom branch lines, one inch and above for mains and tank connections. Our support team can size a full plan for you.</p>'],
            ['Payments', 'Which payment methods do you accept?', '<p>UPI, credit and debit cards, net banking, wallets and cash on delivery.</p>'],
            ['Payments', 'Are prices inclusive of GST?', '<p>Yes. The price you see is the price you pay, and the GST breakup is printed on your invoice.</p>'],
            ['Returns', 'What can be returned?', '<p>Unused products in original packing within 7 days. Cut pipes, opened cement and installed sanitaryware cannot be returned.</p>'],
            ['Returns', 'What if an item arrives damaged?', '<p>Report it from your order page within 48 hours with photos and we replace it free of cost.</p>'],
            ['Bulk orders', 'How do bulk quotes work?', '<p>Send your requirement through the Bulk Quote form or WhatsApp. A project executive replies with pricing and delivery schedule within one working day.</p>'],
        ];
        Faq::where('template', 'plumbing')->delete();
        foreach ($faqs as $i => [$cat, $q, $a]) {
            Faq::create(['category' => $cat, 'question' => $q, 'answer' => $a, 'sort_order' => $i, 'is_active' => true, 'template' => 'plumbing']);
        }

        $posts = [
            ['slug' => 'pvc-vs-cpvc-vs-upvc', 'title' => 'PVC vs CPVC vs UPVC: which pipe for which job?', 'category' => 'Guides', 'excerpt' => 'The three most common plastic pipes in Indian homes, and exactly where each one belongs.', 'image' => $img('1718347791747-35fac02b585e', 1600, 900), 'body' => '<p><strong>CPVC</strong> handles hot water up to 93 °C, so it goes in bathroom and kitchen hot & cold lines and geyser connections. <strong>UPVC</strong> is for cold water supply — tank lines, garden taps, utility areas. <strong>PVC</strong> is the workhorse for drainage, soil and waste stacks, rainwater and agriculture.</p><p>Never mix cements: CPVC cement for CPVC, UPVC cement for UPVC. A quarter-turn on insertion and 24 hours before pressure testing is the rule for all three.</p>'],
            ['slug' => 'choose-a-water-pump', 'title' => 'How to choose a water pump for your home', 'category' => 'Guides', 'excerpt' => 'Self-priming, submersible or booster? Head, suction and discharge explained in plain language.', 'image' => $img('1534641614095-6222aed9bdd6', 1600, 900), 'body' => '<p>Start with the job: filling an overhead tank from a sump needs a <strong>self-priming monoblock</strong>; pulling from a borewell needs a <strong>submersible</strong>; weak showers on the top floor need a <strong>pressure booster</strong>. Then match the total head (vertical lift plus friction) and the discharge you need in litres per hour.</p>'],
            ['slug' => 'bathroom-renovation-checklist', 'title' => 'Bathroom renovation checklist: every fitting you will need', 'category' => 'Checklists', 'excerpt' => 'From concealed bodies to the last angle cock — the complete buying list for a bathroom, in installation order.', 'image' => $img('1584622650111-993a426fbf0a', 1600, 900), 'body' => '<p>Rough-in first: CPVC lines, concealed mixer bodies, floor traps and the WC outlet. Then sanitaryware — basin and WC. Finally the exposed fittings: mixer, overhead shower, health faucet, angle cocks, flexible hoses and accessories. Our Bathroom starter kit collection bundles the essentials.</p>'],
        ];
        foreach ($posts as $post) {
            Post::updateOrCreate(['slug' => $post['slug'], 'template' => 'plumbing'], $post + ['published_at' => now()->subDays(rand(1, 20))]);
        }
    }
}
