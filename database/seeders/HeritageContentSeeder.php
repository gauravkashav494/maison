<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Support\Media;
use Illuminate\Database\Seeder;

/** Pages, FAQs and journal posts for the Heritage Grocery template (tagged `heritage`). */
class HeritageContentSeeder extends Seeder
{
    public function run(): void
    {
        $img = fn (string $id, int $w = 1600, int $h = 900) => Media::unsplash($id, $w, "&h={$h}");

        $pages = [
            'about' => [
                'title' => 'Grown by farmers we know.',
                'eyebrow' => 'Our story',
                'template' => 'about',
                'excerpt' => 'We started in 2016 with three farmer families in Punjab and one promise: staples the way they were before industrial milling — clean, traceable and honest.',
                'image' => $img('1500382017468-9049fed747ef', 2000, 900),
                'meta_title' => 'Our story — single-origin Indian staples',
                'meta_description' => 'How we work with farmer collectives across twelve states to bring stone-ground, cold-pressed, sun-dried Indian staples to your kitchen.',
                'data' => [
                    'sections' => [
                        ['layout' => 'text-image', 'eyebrow' => 'Sourcing', 'heading' => 'Twelve states, 1,200 farmers, one standard', 'body' => '<p>Every product is traced to a farmer collective. We visit at harvest, agree prices above the mandi rate before sowing, and test each lot in an FSSAI-approved laboratory for pesticide residue and adulteration.</p>', 'image' => $img('1464226184884-fa280b87c399', 1200, 900)],
                        ['layout' => 'image-text', 'eyebrow' => 'Processing', 'heading' => 'Stone-ground, cold-pressed, sun-dried', 'body' => '<p>Our flours are chakki-ground weekly, oils are wood-pressed at low temperatures and spices are sun-dried before grinding in small batches — the slow methods that keep aroma, nutrients and character intact.</p>', 'image' => $img('1509358271058-acd22cc93898', 1200, 900)],
                        ['layout' => 'text-image', 'eyebrow' => 'Packaging', 'heading' => 'Packed the week it arrives', 'body' => '<p>Nothing sits in a warehouse for months. Dry fruits are nitrogen-flushed, flours carry their milling date and every pack prints its harvest and origin.</p>', 'image' => $img('1532336414038-cf19250c5757', 1200, 900)],
                    ],
                    'values' => [
                        ['title' => 'Traceable to the farm', 'text' => 'Harvest, origin and lot number on every pack.'],
                        ['title' => 'Fair to farmers', 'text' => 'Prices agreed before sowing, paid within seven days of delivery.'],
                        ['title' => 'Nothing artificial', 'text' => 'No colours, preservatives, refined additives or fillers.'],
                        ['title' => 'Lab-tested', 'text' => 'Every lot tested for residues and adulteration.'],
                    ],
                    'sustainability_heading' => 'Better for the soil, too.',
                    'sustainability_body' => '<p>Sixty percent of our partner farms are certified organic and the rest are in conversion. We fund natural farming training, buy the rain-fed millets that keep dryland farms viable, and ship in paper and reusable tins wherever we can.</p>',
                    'sustainability_image' => $img('1536304993881-ff6e9eefa2a6', 1200, 900),
                    'founder_quote' => 'A good pantry is not about more choice. It is about knowing exactly where each ingredient came from.',
                    'founder_name' => 'Ananya Rao, founder',
                ],
            ],
            'contact' => ['title' => 'We would love to hear from you.', 'eyebrow' => 'Contact', 'template' => 'contact', 'excerpt' => 'Questions about an order, a product or a bulk requirement — we reply within one business day.', 'data' => ['subjects' => ['Order enquiry', 'Product question', 'Bulk / corporate gifting', 'Returns & refunds', 'Farmer partnership', 'Other']]],
            'faq' => ['title' => 'Frequently asked questions', 'eyebrow' => 'Help', 'template' => 'faq', 'excerpt' => 'Ordering, delivery, freshness, certifications and returns — answered.'],
            'shipping' => ['title' => 'Shipping & delivery', 'template' => 'legal', 'excerpt' => 'Pan-India delivery, timelines and charges.', 'body' => '<h3>Coverage</h3><p>We deliver to over 19,000 pincodes across India through our courier partners. Enter your pincode at checkout to see the estimated delivery date.</p><h3>Timelines</h3><p>Metro cities: 2–3 business days. Rest of India: 3–5 business days. Orders placed before 2 pm ship the same day, Monday to Saturday.</p><h3>Charges</h3><p>Free delivery on orders above ₹999. A flat charge, shown at checkout, applies to smaller orders.</p><h3>Packaging</h3><p>Dry fruits are nitrogen-flushed, oils and ghee ship in leak-proof jars with protective wrap, and flours are packed in kraft-paper pouches.</p>'],
            'returns' => ['title' => 'Returns & refunds', 'template' => 'legal', 'excerpt' => 'Our quality promise.', 'body' => '<h3>Quality promise</h3><p>If any product arrives damaged, leaking, expired or not as described, tell us within 7 days of delivery with a photo and we will replace it or refund it in full.</p><h3>What we cannot take back</h3><p>Opened food products cannot be returned for hygiene reasons unless they are defective. Gift boxes may be returned unopened within 7 days.</p><h3>Refunds</h3><p>Refunds are issued to the original payment method within 5–7 business days. Cash-on-delivery orders are refunded by bank transfer.</p>'],
            'privacy' => ['title' => 'Privacy policy', 'template' => 'legal', 'body' => '<h3>What we collect</h3><p>Name, contact details, delivery addresses, order history and, with your consent, browsing preferences that help us recommend products.</p><h3>How we use it</h3><p>To fulfil orders, provide support, improve the store and — if you opt in — send offers and recipes. We never sell personal data.</p><h3>Sharing</h3><p>Couriers receive your name, address and phone number to deliver your order. Payments are handled by PCI-DSS certified gateways; we do not store card details.</p><h3>Your rights</h3><p>Access, correct or delete your data from your account or by writing to us.</p>'],
            'terms' => ['title' => 'Terms & conditions', 'template' => 'legal', 'body' => '<h3>Orders and pricing</h3><p>All prices are in Indian rupees and include GST. An order is confirmed when you receive the confirmation email; we may cancel and refund an order if a product becomes unavailable.</p><h3>Natural variation</h3><p>Our products are natural and minimally processed. Colour, grain size and crystallisation (in honey and ghee) vary between batches and seasons.</p><h3>Delivery</h3><p>Delivery timelines are estimates. Please inspect the parcel on arrival and report damage within 7 days.</p><h3>Accounts</h3><p>You are responsible for keeping your login details secure. We may suspend accounts that abuse offers.</p>'],
            'cookies' => ['title' => 'Cookie policy', 'template' => 'cookies', 'excerpt' => 'What cookies we use and how to control them.', 'body' => '<h3>Essential</h3><p>Cart, login session and your saved preferences.</p><h3>Analytics</h3><p>Anonymous statistics that show us which pages and products help customers.</p><h3>Marketing</h3><p>Personalised offers and recipes. Optional — manage them from the panel on this page.</p>'],
            'payment' => ['title' => 'Payment options', 'template' => 'legal', 'excerpt' => 'Accepted methods and security.', 'body' => '<h3>Accepted methods</h3><ul><li>UPI — Google Pay, PhonePe, Paytm and any UPI app</li><li>Credit and debit cards — Visa, Mastercard, RuPay, American Express</li><li>Net banking from all major Indian banks</li><li>Wallets — Paytm, Amazon Pay</li><li>Cash on delivery</li></ul><h3>Security</h3><p>Payments are processed by PCI-DSS Level 1 certified partners over encrypted connections. We never see or store your card number.</p>'],
            'gift-cards' => ['title' => 'Give the gift of a good pantry.', 'eyebrow' => 'Gift cards', 'template' => 'gift-cards', 'excerpt' => 'Digital gift cards delivered by email — for housewarmings, weddings and festivals.', 'image' => $img('1512909006721-3d6018887383', 1600, 800), 'data' => ['steps' => [['title' => 'Choose an amount', 'text' => 'From ₹500 to ₹10,000.'], ['title' => 'Add a message', 'text' => 'We design the card around your note.'], ['title' => 'Delivered by email', 'text' => 'Instantly, or on a date you choose.']]]],
            'careers' => ['title' => 'Work with us.', 'eyebrow' => 'Careers', 'template' => 'careers', 'excerpt' => 'Sourcing, quality, operations and technology — join a team that visits the farms it buys from.', 'image' => $img('1464226184884-fa280b87c399', 1600, 800), 'data' => ['roles' => [['title' => 'Sourcing Manager — Spices', 'location' => 'Kochi', 'type' => 'Full-time', 'summary' => 'Build relationships with spice-growing collectives in Kerala and Karnataka; own quality from harvest to pack.'], ['title' => 'Quality Analyst', 'location' => 'Bengaluru', 'type' => 'Full-time', 'summary' => 'Run our lab-testing programme and the batch traceability system.'], ['title' => 'Customer Care Associate', 'location' => 'Remote (India)', 'type' => 'Full-time', 'summary' => 'Help customers with orders, recipes and product questions across chat, phone and email.']], 'perks' => ['Monthly pantry allowance', 'Farm visits with the sourcing team', 'Health insurance for you and family', 'Flexible working for office roles']]],
            'stores' => ['title' => 'Our stores', 'eyebrow' => 'Visit us', 'template' => 'stores', 'excerpt' => 'Taste before you buy, pick up online orders and meet the team.'],
        ];
        foreach ($pages as $slug => $attrs) {
            Page::updateOrCreate(['slug' => $slug, 'storefront_template' => 'heritage'], $attrs + ['is_active' => true, 'body' => $attrs['body'] ?? null]);
        }

        $faqs = [
            ['Orders', 'How long does delivery take?', '<p>2–3 business days to metro cities and 3–5 days elsewhere. Orders placed before 2 pm ship the same day.</p>'],
            ['Orders', 'Is there free delivery?', '<p>Yes — on all orders above ₹999. Smaller orders carry a flat charge shown at checkout.</p>'],
            ['Orders', 'Can I order in bulk or for corporate gifting?', '<p>Yes. Write to us through the contact page with quantities and we will send a quote within one business day.</p>'],
            ['Products', 'Are your products certified organic?', '<p>Products in the Organic Foods section are India Organic / NPOP certified. Other products are natural and minimally processed but may come from farms still in conversion.</p>'],
            ['Products', 'Why does my honey or ghee look different from the last jar?', '<p>Natural products vary by season. Raw honey crystallises and ghee turns granular in cooler weather — both are signs of purity, not spoilage.</p>'],
            ['Products', 'What do the dietary tags mean?', '<p>Tags such as Organic, Vegan, Gluten-free and Stone-ground describe how a product was grown or made. Use the dietary filter on any listing to shop by them.</p>'],
            ['Products', 'How do I know the harvest date?', '<p>Every pack prints its harvest or milling date, origin and lot number. Flours are milled weekly; dry fruits are nitrogen-flushed for freshness.</p>'],
            ['Shipping', 'Do you deliver outside India?', '<p>Not yet. We currently deliver to 19,000+ pincodes within India.</p>'],
            ['Returns', 'What if my order arrives damaged?', '<p>Send us a photo within 7 days and we will replace or refund it in full.</p>'],
            ['Returns', 'Can I return an opened product?', '<p>For hygiene reasons opened food cannot be returned unless it is defective. Unopened gift boxes can be returned within 7 days.</p>'],
            ['Payments', 'Which payment methods do you accept?', '<p>UPI, credit and debit cards, net banking, wallets and cash on delivery.</p>'],
            ['Account', 'Do I need an account?', '<p>You can check out as a guest. An account saves addresses, tracks orders and lets you reorder your pantry in one tap.</p>'],
            ['General', 'Where do your products come from?', '<p>From farmer collectives in twelve Indian states — Punjab and Haryana for wheat and basmati, Kerala for spices and red rice, Karnataka for millets, Meghalaya for turmeric, the Nilgiris for honey.</p>'],
        ];
        Faq::where('template', 'heritage')->delete();
        foreach ($faqs as $i => [$cat, $q, $a]) {
            Faq::create(['category' => $cat, 'question' => $q, 'answer' => $a, 'sort_order' => $i, 'is_active' => true, 'template' => 'heritage']);
        }

        $posts = [
            ['slug' => 'perfect-basmati-every-time', 'title' => 'How to cook perfect basmati, every time', 'category' => 'Kitchen notes', 'excerpt' => 'Rinse, soak, ratio, rest — the four steps that turn aged basmati into long, separate, fragrant grains.', 'image' => $img('1536304993881-ff6e9eefa2a6', 1400, 900), 'read_time' => 4, 'is_featured' => true, 'body' => '<h2>1. Rinse</h2><p>Wash the rice in cold water until it runs clear — this removes surface starch that makes grains stick.</p><h2>2. Soak</h2><p>Twenty minutes in cold water lets the grains hydrate evenly and lengthen fully.</p><h2>3. Ratio</h2><p>1 cup rice to 1.5 cups water for the absorption method; a pinch of salt and a teaspoon of ghee.</p><h2>4. Rest</h2><p>Once the water is absorbed, take it off the heat and leave it covered for 10 minutes. Fluff with a fork, never a spoon.</p>'],
            ['slug' => 'why-bilona-ghee-is-different', 'title' => 'Why bilona ghee tastes different', 'category' => 'Stories', 'excerpt' => 'Thirty litres of milk, a day of culturing, and a wooden churn: the slow method behind our A2 ghee.', 'image' => $img('1589985270826-4b7bb135bc9d', 1400, 900), 'read_time' => 5, 'body' => '<p>Most commercial ghee is made by boiling cream. Bilona ghee starts with milk that is cultured into curd overnight, churned with a wooden bilona to separate the butter, and only then slowly simmered. It takes 30 litres of A2 milk to make one kilo — which is why it is granular, nutty and fragrant in a way boiled-cream ghee never is.</p>'],
            ['slug' => 'the-everyday-masala-dabba', 'title' => 'Building the everyday masala dabba', 'category' => 'Kitchen notes', 'excerpt' => 'Seven spices that cook ninety percent of Indian home food — and how to keep them fresh.', 'image' => $img('1506368249639-73a05d6f6488', 1400, 900), 'read_time' => 3, 'body' => '<p>Turmeric, red chilli, coriander, cumin, mustard seeds, garam masala and asafoetida. Buy whole where you can, grind in small batches, and keep the dabba away from the stove — heat and light are the enemies of aroma.</p>'],
        ];
        foreach ($posts as $post) {
            Post::updateOrCreate(['slug' => $post['slug'], 'template' => 'heritage'], $post + ['published_at' => now()->subDays(rand(2, 30))]);
        }
    }
}
