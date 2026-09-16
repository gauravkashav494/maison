<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Support\Media;
use Illuminate\Database\Seeder;

class PagesSeeder extends Seeder
{
    public function run(): void
    {
        $u = fn (string $id, int $w = 1400, int $h = 1750) => Media::unsplash($id, $w, "&h={$h}");

        $pages = [
            'about' => [
                'title' => 'Designed with intention. Made to last.',
                'eyebrow' => 'Our story',
                'template' => 'about',
                'excerpt' => 'Maison Élan began with a simple question: what would a wardrobe look like if every piece earned its place?',
                'image' => $u('1534126511673-b6899657816a', 2000, 1200),
                'meta_title' => 'About Maison Élan — Our Story',
                'meta_description' => 'The story, philosophy and craftsmanship behind Maison Élan — clothing, fragrance, timepieces and leather goods designed with intention.',
                'data' => [
                    'sections' => [
                        ['layout' => 'text-image', 'eyebrow' => 'Brand story', 'heading' => 'Founded in a small atelier, with a *large* idea.', 'body' => "Maison Élan was founded in 2018 in a converted textile mill in Bengaluru by two friends — a pattern cutter and a perfumer — who shared a frustration with fashion that was loud, fast and forgettable.\n\nThe first collection was twelve pieces. Each was revised until nothing could be removed. That discipline still shapes everything the house makes.", 'image' => $u('1571513722275-4b41940f54b8')],
                        ['layout' => 'image-text', 'eyebrow' => 'Our philosophy', 'heading' => 'Quiet confidence over *noise*.', 'body' => "We believe the most luxurious thing a garment can do is disappear — to feel so right that you stop thinking about it. So we obsess over proportion, weight and the way cloth moves, and we leave the logos off.\n\nEvery season we add a few pieces and retire almost none. A wardrobe should compound, not churn.", 'image' => $u('1526413232644-8a40f03cc03b')],
                        ['layout' => 'text-image', 'eyebrow' => 'Craftsmanship', 'heading' => 'Three ateliers. Hundreds of hands.', 'body' => "Tailoring is cut and sewn in our Bengaluru atelier. Leather goods are made in a family workshop in Chennai that has tanned vegetable leather for four generations. Fragrances are composed with master perfumers in Grasse.\n\nWe visit every partner, every year. If we would not be proud to show you the workshop, we do not work with it.", 'image' => $u('1534126511673-b6899657816a')],
                    ],
                    'values' => [
                        ['title' => 'Material first', 'text' => 'Natural fibres, full-grain leathers and Swiss movements — chosen for how they age, not how they photograph.'],
                        ['title' => 'Made in small runs', 'text' => 'We produce close to demand so nothing is discounted into landfill.'],
                        ['title' => 'Repair, always', 'text' => 'Every piece can be returned to us for repair for as long as you own it.'],
                        ['title' => 'Honest pricing', 'text' => 'No inflated list prices, no permanent sales. One fair price, all year.'],
                    ],
                    'sustainability_heading' => 'Responsible by design, not by press release.',
                    'sustainability_body' => "Eighty percent of our fabrics are natural and certified. Our leather is vegetable-tanned without chromium. Packaging is FSC card and cotton, and our boutiques run on renewable energy.\n\nWe publish a short impact note every year. It is written by us, not an agency, and it includes the things we have not solved yet.",
                    'sustainability_image' => $u('1558769132-cb1aea458c5e'),
                    'founder_quote' => 'We wanted to make the pieces people reach for without thinking — and keep reaching for, for years.',
                    'founder_name' => 'Aanya Mehra & Julien Roche, founders',
                ],
            ],
            'contact' => [
                'title' => 'We are here to help.',
                'eyebrow' => 'Contact',
                'template' => 'contact',
                'excerpt' => 'Questions about an order, a fitting, or a gift? Our client care team replies within one business day.',
                'meta_description' => 'Contact Maison Élan client care by email, phone or the form below. Monday to Saturday, 10:00–19:00 IST.',
                'data' => ['subjects' => ['Order enquiry', 'Returns & exchanges', 'Product question', 'Sizing advice', 'Press', 'Other']],
            ],
            'faq' => [
                'title' => 'Frequently asked questions',
                'eyebrow' => 'Help',
                'template' => 'faq',
                'excerpt' => 'Everything you need to know about ordering, delivery, returns and caring for your pieces.',
            ],
            'shipping' => [
                'title' => 'Shipping Information',
                'template' => 'legal',
                'excerpt' => 'How and when your order arrives.',
                'body' => $this->shippingBody(),
            ],
            'returns' => [
                'title' => 'Returns & Exchanges',
                'template' => 'legal',
                'excerpt' => '30 days, collected from your door, no charge.',
                'body' => $this->returnsBody(),
                'data' => ['cta_label' => 'Start a return', 'cta_url' => '/account/orders'],
            ],
            'privacy' => ['title' => 'Privacy Policy', 'template' => 'legal', 'body' => $this->privacyBody()],
            'terms' => ['title' => 'Terms & Conditions', 'template' => 'legal', 'body' => $this->termsBody()],
            'cookies' => ['title' => 'Cookie Policy', 'template' => 'cookies', 'excerpt' => 'What cookies we use, why, and how to control them.', 'body' => $this->cookiesBody()],
            'accessibility' => ['title' => 'Accessibility Statement', 'template' => 'legal', 'body' => '<p>Maison Élan is committed to making our website usable by everyone, regardless of ability or technology. We aim to conform to WCAG 2.2 Level AA.</p><h3>What we do</h3><ul><li>Semantic HTML, keyboard-navigable menus, drawers and forms</li><li>Visible focus states and a “skip to content” link</li><li>Alternative text for product and campaign imagery</li><li>Colour contrast that meets AA on all text</li><li>Reduced-motion support: animations are disabled when your system requests it</li></ul><h3>Feedback</h3><p>If you encounter a barrier, please email <a href="mailto:care@maisonelan.com">care@maisonelan.com</a> with the page address and a description. We respond within two business days and prioritise fixes.</p>'],
            'disclaimer' => ['title' => 'Disclaimer', 'template' => 'legal', 'body' => '<p>The information on this website is provided in good faith and for general information only. While we make every effort to keep product descriptions, pricing and availability accurate, errors may occur. We reserve the right to correct any error and to cancel orders affected by it, with a full refund.</p><p>Colours may vary slightly from those shown on screen depending on your device. Natural materials such as leather and wool have inherent variations that are part of their character, not defects.</p><p>External links are provided for convenience; we are not responsible for the content of third-party sites.</p>'],
            'payment' => ['title' => 'Payment Information', 'template' => 'legal', 'excerpt' => 'Accepted methods, security and billing.', 'body' => '<h3>Accepted methods</h3><ul><li>Credit and debit cards: Visa, Mastercard, American Express, RuPay</li><li>UPI (Google Pay, PhonePe, Paytm and any UPI app)</li><li>Net banking from all major Indian banks</li><li>Wallets: Paytm, Amazon Pay, PhonePe</li><li>Cash on delivery within India (₹99 handling fee)</li></ul><h3>Security</h3><p>Payments are processed over encrypted connections by PCI-DSS Level 1 certified partners. We never see or store your full card number.</p><h3>Billing</h3><p>Your card is charged when the order is placed. If an item cannot be fulfilled we refund it immediately to the original payment method; refunds appear within 5–7 business days depending on your bank.</p><h3>Pricing</h3><p>All prices are in Indian Rupees and include GST. International orders show duties and taxes at checkout so there is nothing to pay on delivery.</p>'],
            'size-guide' => [
                'title' => 'Size Guide',
                'eyebrow' => 'Fit',
                'template' => 'size-guide',
                'excerpt' => 'Measurements in centimetres. If you are between sizes, size up for outerwear and down for knitwear.',
                'data' => [
                    'tables' => [
                        ['name' => 'Womenswear', 'columns' => ['Size', 'Bust', 'Waist', 'Hips'], 'rows' => [['XS', '80–84', '62–66', '88–92'], ['S', '84–88', '66–70', '92–96'], ['M', '88–92', '70–74', '96–100'], ['L', '92–97', '74–79', '100–105'], ['XL', '97–102', '79–84', '105–110']]],
                        ['name' => 'Menswear', 'columns' => ['Size', 'Chest', 'Waist', 'Shoulder'], 'rows' => [['S', '92–96', '78–82', '44'], ['M', '96–100', '82–86', '46'], ['L', '100–104', '86–90', '48'], ['XL', '104–108', '90–94', '50'], ['XXL', '108–112', '94–98', '52']]],
                        ['name' => 'Belts', 'columns' => ['Size', 'Waist (cm)', 'Waist (in)'], 'rows' => [['80', '76–80', '30–31'], ['85', '81–85', '32–33'], ['90', '86–90', '34–35'], ['95', '91–95', '36–37'], ['100', '96–100', '38–39']]],
                        ['name' => 'Footwear', 'columns' => ['EU', 'UK', 'US', 'Foot length (cm)'], 'rows' => [['40', '6', '7', '25.4'], ['41', '7', '8', '26.0'], ['42', '8', '9', '26.7'], ['43', '9', '10', '27.3'], ['44', '10', '11', '28.0'], ['45', '11', '12', '28.6']]],
                    ],
                    'how_to' => [
                        ['title' => 'Bust / Chest', 'text' => 'Measure around the fullest part, keeping the tape level and relaxed.'],
                        ['title' => 'Waist', 'text' => 'Measure around the narrowest part of your natural waistline.'],
                        ['title' => 'Hips', 'text' => 'Stand with feet together and measure around the fullest part.'],
                        ['title' => 'Shoulder', 'text' => 'Measure from shoulder seam to shoulder seam across the back.'],
                    ],
                ],
            ],
            'care-guide' => [
                'title' => 'Care Guide',
                'eyebrow' => 'Longevity',
                'template' => 'care-guide',
                'excerpt' => 'Pieces made to last deserve to be looked after. A few habits make the difference between years and decades.',
                'data' => ['materials' => [
                    ['name' => 'Wool & cashmere', 'image' => $u('1539533018447-63fcce2678e3', 900, 1100), 'tips' => ['Air after wearing; wash rarely and by hand in cool water', 'Dry flat away from direct heat', 'Store folded with cedar; never hang knitwear', 'Remove pilling gently with a comb']],
                    ['name' => 'Leather', 'image' => $u('1521223890158-f9f7c3d5d504', 900, 1100), 'tips' => ['Condition every few months with a neutral balm', 'Keep away from prolonged sun and heat', 'Stuff bags with tissue and store in the dust bag', 'Let wet leather dry naturally, never on a radiator']],
                    ['name' => 'Cotton & linen', 'image' => $u('1558769132-cb1aea458c5e', 900, 1100), 'tips' => ['Machine wash cool with like colours', 'Line dry; tumble drying shortens fibre life', 'Iron while slightly damp for a crisp finish']],
                    ['name' => 'Watches', 'image' => $u('1619134778706-7015533a6150', 900, 1100), 'tips' => ['Wipe the case and bracelet with a soft cloth', 'Have automatics serviced every 4–5 years', 'Avoid magnets and sudden temperature changes', 'Do not operate the crown under water']],
                    ['name' => 'Fragrance', 'image' => $u('1622618991746-fe6004db3a47', 900, 1100), 'tips' => ['Store in a cool, dark place — not the bathroom', 'Keep the cap on to slow oxidation', 'Apply to pulse points; do not rub']],
                    ['name' => 'Jewellery', 'image' => $u('1602173574767-37ac01994b2a', 900, 1100), 'tips' => ['Put on last, take off first', 'Polish with the supplied cloth', 'Store pieces separately to prevent scratching', 'Keep pearls away from perfume and hairspray']],
                ]],
            ],
            'gift-cards' => [
                'title' => 'The gift of choosing.',
                'eyebrow' => 'Gift cards',
                'template' => 'gift-cards',
                'excerpt' => 'Delivered by email within the hour, redeemable on every piece in the house, and never expiring.',
                'image' => $u('1617038220319-276d3cfab638', 1600, 1000),
                'data' => ['steps' => [
                    ['title' => 'Choose a value', 'text' => 'From ₹2,500 to ₹25,000, or add several to your bag.'],
                    ['title' => 'Add a note', 'text' => 'Tell us who it is for and what to say. We will design the card around it.'],
                    ['title' => 'Sent within the hour', 'text' => 'Delivered by email to you or straight to the recipient, on the day you choose.'],
                ]],
            ],
            'careers' => [
                'title' => 'Work with us.',
                'eyebrow' => 'Careers',
                'template' => 'careers',
                'excerpt' => 'We are a small team of makers, thinkers and hosts. If you care about doing fewer things exceptionally well, we would like to meet you.',
                'image' => $u('1534126511673-b6899657816a', 1600, 1000),
                'data' => ['roles' => [
                    ['title' => 'Senior Pattern Cutter', 'location' => 'Bengaluru atelier', 'type' => 'Full-time', 'summary' => 'Lead the development of tailoring blocks and work directly with the design team from toile to production.'],
                    ['title' => 'Client Advisor', 'location' => 'Mumbai boutique', 'type' => 'Full-time', 'summary' => 'Host clients in the boutique, build lasting relationships and represent the house with warmth and knowledge.'],
                    ['title' => 'E-commerce Merchandiser', 'location' => 'Bengaluru / hybrid', 'type' => 'Full-time', 'summary' => 'Own the storefront: product data, editorial placement, and the numbers behind what we show and when.'],
                    ['title' => 'Leather Craftsperson', 'location' => 'Chennai workshop', 'type' => 'Full-time', 'summary' => 'Cut, skive and stitch small leather goods to the house standard alongside our partner atelier.'],
                ], 'perks' => ['Wardrobe allowance on every collection', 'Annual atelier residency', 'Health cover for you and family', 'Hybrid working for office roles']],
            ],
            'stores' => [
                'title' => 'Visit us.',
                'eyebrow' => 'Boutiques',
                'template' => 'stores',
                'excerpt' => 'Three boutiques, each with fitting rooms, a fragrance bar and client advisors who know the collection inside out.',
            ],
        ];

        foreach ($pages as $slug => $attrs) {
            Page::updateOrCreate(['slug' => $slug], $attrs + ['is_active' => true, 'body' => $attrs['body'] ?? null]);
        }
    }

    private function shippingBody(): string
    {
        return <<<'HTML'
<h3>Shipping methods</h3>
<ul>
<li><strong>Standard delivery</strong> — ₹250, complimentary over ₹15,000. Tracked courier, 3–5 business days.</li>
<li><strong>Express delivery</strong> — ₹650, complimentary over ₹40,000. 1–2 business days to metro cities.</li>
<li><strong>Collect in boutique</strong> — complimentary. Ready within 24 hours at Bengaluru, Mumbai or New Delhi.</li>
</ul>
<h3>Delivery timelines</h3>
<p>Orders placed before 14:00 IST on a business day are dispatched the same day. Timelines are counted from dispatch and exclude Sundays and public holidays. Pre-order and made-to-measure pieces show their own estimated date on the product page.</p>
<h3>Shipping charges</h3>
<p>Charges are shown at checkout before you pay. There are no additional handling fees except a ₹99 fee for cash on delivery.</p>
<h3>International shipping</h3>
<p>We ship to over 40 countries with DHL Express in 5–10 business days. Duties and taxes are calculated and collected at checkout so nothing is due on delivery. Fragrances cannot be shipped to some destinations by air; checkout will let you know.</p>
<h3>Order processing</h3>
<p>You will receive a confirmation email when your order is placed and a second email with tracking when it is dispatched. Every piece is inspected, folded in tissue and packed in our ivory box.</p>
<h3>Tracking</h3>
<p>Use the tracking link in your dispatch email, sign in to your account, or visit <a href="/track-order">Track your order</a> with your order number and email.</p>
<h3>Delays</h3>
<p>Weather, customs or courier issues can occasionally delay delivery. If your order is more than three business days past its estimate, contact client care and we will investigate and, where appropriate, refund shipping.</p>
<h3>Address changes</h3>
<p>Addresses can be changed until the order is dispatched. Contact client care as soon as possible with your order number.</p>
<h3>Lost or damaged packages</h3>
<p>If your package arrives damaged, photograph the box and contents and contact us within 48 hours. If a tracked package is confirmed lost by the courier, we will replace it or refund you in full.</p>
HTML;
    }

    private function returnsBody(): string
    {
        return <<<'HTML'
<h3>Return eligibility</h3>
<p>Any piece may be returned within 30 days of delivery provided it is unworn, unwashed, with all tags and packaging intact. Footwear must be tried on indoors and returned in its box.</p>
<h3>Return window</h3>
<p>30 days from the date of delivery. Gifts may be returned for store credit within 30 days of the original delivery date.</p>
<h3>Exchange policy</h3>
<p>Exchanges for another size or colour are complimentary. Request an exchange from your order page; the replacement is dispatched as soon as the original is collected.</p>
<h3>Refund process and timelines</h3>
<p>Once your return reaches our warehouse it is inspected within two business days. Refunds are issued to the original payment method and typically appear within 5–7 business days depending on your bank. Cash on delivery orders are refunded by bank transfer.</p>
<h3>Non-returnable products</h3>
<ul>
<li>Fragrances that have been opened or unsealed</li>
<li>Earrings, for hygiene reasons</li>
<li>Gift cards</li>
<li>Pieces altered or personalised at your request</li>
</ul>
<h3>Damaged or incorrect products</h3>
<p>If anything arrives damaged or is not what you ordered, contact client care within 48 hours with photographs. We will collect the item and send a replacement or full refund, including any shipping paid.</p>
<h3>Return shipping</h3>
<p>Returns within India are collected from your address at no charge. International returns are sent with the prepaid label included in your parcel; the original shipping fee is not refunded.</p>
<h3>How to initiate a return</h3>
<ol>
<li>Sign in and open the order under <a href="/account/orders">My orders</a>, or contact client care with your order number.</li>
<li>Choose the items and reason. We will confirm a collection slot within 48 hours.</li>
<li>Pack the pieces in the original packaging with the tags attached.</li>
<li>Hand the parcel to the courier and keep the receipt until your refund is confirmed.</li>
</ol>
HTML;
    }

    private function privacyBody(): string
    {
        return <<<'HTML'
<p>This policy explains what personal information Maison Élan collects, how we use it and the choices you have. It applies to our website, boutiques and client care.</p>
<h3>Information we collect</h3>
<p>Contact details (name, email, phone, addresses), order and payment information, account credentials, your communications with us, and technical data such as device, browser and pages visited.</p>
<h3>How we use it</h3>
<p>To fulfil and deliver orders, provide client care, personalise your experience, prevent fraud, improve the website and — with your consent — send marketing communications.</p>
<h3>Cookies</h3>
<p>We use essential cookies to run the site and, with your permission, analytics and marketing cookies. See our <a href="/cookies">Cookie Policy</a> for details and controls.</p>
<h3>Analytics</h3>
<p>Aggregated, anonymised analytics help us understand how the site is used. Analytics cookies are only set if you accept them.</p>
<h3>Payment information</h3>
<p>Payments are processed by PCI-DSS certified providers. We receive confirmation of payment and the last four digits of your card; we never store full card numbers.</p>
<h3>Third-party services</h3>
<p>We share data only with providers who help us operate: couriers, payment processors, email services and analytics. Each is bound by contract to protect your data and use it only for our instructions.</p>
<h3>Marketing communications</h3>
<p>You will receive marketing emails only if you subscribe. Every email includes an unsubscribe link, and you can change preferences in your account at any time.</p>
<h3>Data security</h3>
<p>Data is encrypted in transit and at rest, access is limited to staff who need it, and we review our safeguards regularly.</p>
<h3>Your rights</h3>
<p>You may request access to, correction of, or deletion of your data, object to processing, or ask for a portable copy. Email <a href="mailto:care@maisonelan.com">care@maisonelan.com</a> and we will respond within 30 days.</p>
<h3>Data retention</h3>
<p>Order records are kept for seven years to meet tax and legal obligations. Account data is kept while your account is active and deleted on request.</p>
<h3>Contact</h3>
<p>Maison Élan Private Limited, 12 Lavelle Road, Bengaluru 560001, India · <a href="mailto:care@maisonelan.com">care@maisonelan.com</a></p>
HTML;
    }

    private function termsBody(): string
    {
        return <<<'HTML'
<p>By using this website you agree to these terms. Please read them carefully.</p>
<h3>Website usage</h3>
<p>You may use the site for personal, non-commercial purposes. You must not misuse it, attempt to gain unauthorised access, or use automated tools to scrape content.</p>
<h3>Account registration</h3>
<p>You are responsible for keeping your credentials confidential and for all activity under your account. Please tell us immediately if you suspect unauthorised use.</p>
<h3>Product information</h3>
<p>We take care to describe products accurately. Minor variations in colour and natural materials are not defects. Product availability is not guaranteed until an order is confirmed.</p>
<h3>Pricing</h3>
<p>Prices are in Indian Rupees and include GST unless stated. We may change prices at any time; the price shown at checkout is the price you pay. Obvious pricing errors may be corrected and affected orders cancelled with a full refund.</p>
<h3>Orders</h3>
<p>Your order is an offer to purchase. A contract is formed when we dispatch the goods. We may refuse or cancel orders for reasons including suspected fraud, stock errors or address problems.</p>
<h3>Payments</h3>
<p>Payment is taken when the order is placed. You confirm that you are authorised to use the payment method provided.</p>
<h3>Intellectual property</h3>
<p>All content — designs, photography, text, logos — belongs to Maison Élan or its licensors and may not be reproduced without written permission.</p>
<h3>User content</h3>
<p>By submitting reviews or messages you grant us a non-exclusive licence to use them. Content must be honest, lawful and respectful; we may remove content that is not.</p>
<h3>Limitation of liability</h3>
<p>To the extent permitted by law, our liability for any claim relating to an order is limited to the amount paid for that order. Nothing limits liability for death, personal injury or fraud.</p>
<h3>Governing law</h3>
<p>These terms are governed by the laws of India. Disputes are subject to the exclusive jurisdiction of the courts of Bengaluru.</p>
<h3>Contact</h3>
<p>Maison Élan Private Limited, 12 Lavelle Road, Bengaluru 560001 · <a href="mailto:care@maisonelan.com">care@maisonelan.com</a></p>
HTML;
    }

    private function cookiesBody(): string
    {
        return <<<'HTML'
<h3>What cookies are</h3>
<p>Cookies are small text files stored on your device by your browser. They let a website remember your choices, keep you signed in and understand how the site is used.</p>
<h3>Essential cookies</h3>
<p>Required for the site to work: your session, shopping bag, sign-in state and security tokens. These cannot be switched off.</p>
<h3>Analytics cookies</h3>
<p>Help us understand which pages are used and where the site could be improved. Data is aggregated and anonymised. Set only with your consent.</p>
<h3>Marketing cookies</h3>
<p>Used to measure campaigns and show relevant content on other platforms. Set only with your consent.</p>
<h3>Third-party cookies</h3>
<p>Some cookies are set by partners such as analytics and payment providers. We only work with partners who meet our privacy standards.</p>
<h3>Cookie preferences</h3>
<p>Use the controls below to change your preferences at any time. You can also delete cookies through your browser settings.</p>
HTML;
    }
}
