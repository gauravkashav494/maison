<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Faq;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Store;
use App\Support\Media;
use Illuminate\Database\Seeder;

class CommerceSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('checkout', [
            'shipping_methods' => [
                ['code' => 'standard', 'name' => 'Standard delivery', 'description' => 'Tracked courier, 3–5 business days', 'cost' => 250, 'free_over' => 15000, 'eta' => '3–5 business days'],
                ['code' => 'express', 'name' => 'Express delivery', 'description' => 'Priority courier, 1–2 business days', 'cost' => 650, 'free_over' => 40000, 'eta' => '1–2 business days'],
                ['code' => 'pickup', 'name' => 'Collect in boutique', 'description' => 'Ready within 24 hours at our Bengaluru boutique', 'cost' => 0, 'free_over' => null, 'eta' => 'Next day'],
            ],
            'payment_methods' => ['card', 'upi', 'wallet', 'netbanking', 'cod'],
            'cod_fee' => 99,
            'tax_rate' => 0,
            'tax_label' => 'GST',
            'gift_card_amounts' => [2500, 5000, 10000, 25000],
        ]);

        foreach ([
            ['code' => 'WELCOME10', 'description' => '10% off your first order', 'type' => 'percent', 'value' => 10, 'min_subtotal' => 5000],
            ['code' => 'ELAN2000', 'description' => '₹2,000 off orders over ₹20,000', 'type' => 'fixed', 'value' => 2000, 'min_subtotal' => 20000],
            ['code' => 'FREESHIP', 'description' => 'Complimentary shipping', 'type' => 'free_shipping', 'value' => 0, 'min_subtotal' => 0],
        ] as $c) {
            Coupon::updateOrCreate(['code' => $c['code']], $c + ['is_active' => true]);
        }

        // Product attributes used by the shop filters
        $materials = [
            'camel-wool-wrap-coat' => 'Wool', 'ivory-tailored-blazer' => 'Wool crêpe', 'noir-leather-biker-jacket' => 'Lambskin',
            'essential-cotton-tee' => 'Organic cotton', 'chambray-relaxed-shirt' => 'Cotton chambray', 'structured-leather-tote' => 'Calf leather',
            'bordeaux-croc-mini-bag' => 'Calf leather', 'signature-leather-belt' => 'Bridle leather', 'bifold-leather-wallet' => 'Calf leather',
            'leather-derby-boots' => 'Calf leather', 'heritage-automatic-watch' => 'Stainless steel', 'minimal-slim-watch' => 'Stainless steel',
            'gold-chain-bracelet' => '18k gold plate', 'sapphire-drop-earrings' => 'Sterling silver', 'pearl-strand-necklace' => 'Freshwater pearl',
            'round-metal-sunglasses' => 'Titanium',
        ];
        foreach ($materials as $slug => $material) {
            Product::where('slug', $slug)->update(['material' => $material, 'brand' => 'Maison Élan']);
        }
        Product::whereIn('slug', ['heritage-automatic-watch', 'minimal-slim-watch'])->update(['brand' => 'Élan Horlogerie']);
        Product::whereIn('slug', ['amber-oud-eau-de-parfum', 'rose-vetiver-eau-de-parfum'])->update(['brand' => 'Élan Parfums', 'material' => null]);

        // Gift card sold as a product with fixed denominations
        $gifts = Category::firstOrCreate(['slug' => 'gift-cards'], ['name' => 'Gift Cards', 'is_active' => true, 'show_in_menu' => false, 'sort_order' => 99]);
        Product::updateOrCreate(['slug' => 'gift-card'], [
            'name' => 'Maison Élan Gift Card',
            'category_id' => $gifts->id,
            'sku' => 'ME-GIFT',
            'price' => 5000,
            'images' => [Media::unsplash('1617038220319-276d3cfab638', 900, '&h=1200')],
            'colors' => [],
            'sizes' => ['₹2,500', '₹5,000', '₹10,000', '₹25,000'],
            'stock' => 9999,
            'is_active' => true,
            'description' => 'A digital gift card delivered by email, redeemable on any piece in the house. Choose a value at checkout.',
            'details' => '<p>Gift cards are delivered by email within an hour of purchase and never expire. They can be redeemed online in a single transaction or across several.</p>',
        ]);

        // Sample approved reviews
        $reviews = [
            ['camel-wool-wrap-coat', 'Priya S.', 5, 'Worth every rupee', 'The drape is extraordinary and the wool is warm without weight. I have worn it every day since it arrived.'],
            ['camel-wool-wrap-coat', 'Aditi R.', 5, 'Beautifully made', 'Runs true to size. The belt detail is elegant and the lining feels luxurious.'],
            ['camel-wool-wrap-coat', 'Karan M.', 4, 'Elegant', 'Gorgeous colour. Took a star off only because delivery took a day longer than promised.'],
            ['amber-oud-eau-de-parfum', 'Nikhil T.', 5, 'My signature now', 'Smoky, warm and lasts all day. I get asked about it constantly.'],
            ['amber-oud-eau-de-parfum', 'Sana K.', 5, 'Sophisticated', 'Opens bright and settles into something deep and quiet. Perfect for evenings.'],
            ['heritage-automatic-watch', 'Rohit V.', 5, 'Heirloom quality', 'Movement is smooth, bracelet is comfortable and the dial is stunning in daylight.'],
            ['signature-leather-belt', 'Arjun D.', 5, 'The last belt I will buy', 'Thick bridle leather that is already developing a beautiful patina.'],
            ['structured-leather-tote', 'Meera P.', 4, 'Structured and roomy', 'Fits a 14-inch laptop with room to spare. Leather is stiff at first but softens.'],
        ];
        foreach ($reviews as [$slug, $name, $rating, $title, $body]) {
            $product = Product::where('slug', $slug)->first();
            if ($product) {
                Review::firstOrCreate(['product_id' => $product->id, 'name' => $name, 'title' => $title], ['rating' => $rating, 'body' => $body, 'is_approved' => true]);
            }
        }

        $faqs = [
            ['Orders', 'How do I place an order?', 'Add pieces to your bag, proceed to checkout and follow the steps for contact details, delivery address and payment. You will receive a confirmation email with your order number immediately.'],
            ['Orders', 'Can I change or cancel my order?', 'Orders can be changed or cancelled within two hours of being placed. Contact client care with your order number and we will do our best to help before the order is dispatched.'],
            ['Orders', 'Where is my order confirmation?', 'Confirmations are sent within minutes to the email used at checkout. Please check your spam folder; if it is still missing, contact client care.'],
            ['Shipping', 'How long does delivery take?', 'Standard delivery takes 3–5 business days across India. Express delivery arrives in 1–2 business days in metro cities. International delivery takes 5–10 business days.'],
            ['Shipping', 'Do you offer free shipping?', 'Yes — standard delivery is complimentary on orders over ₹15,000 and express delivery is complimentary over ₹40,000.'],
            ['Shipping', 'Do you ship internationally?', 'We ship to over 40 countries. Duties and taxes are calculated at checkout so there are no surprises on delivery.'],
            ['Returns', 'What is your return policy?', 'You have 30 days from delivery to return any unworn piece with its tags attached for a full refund. Returns are collected from your door at no charge.'],
            ['Returns', 'How do I start a return?', 'Sign in to your account, open the order and choose “Start a return”, or contact client care with your order number. We will arrange collection within 48 hours.'],
            ['Exchanges', 'Can I exchange for a different size?', 'Yes. Request an exchange within 30 days and we will send the new size as soon as the original is collected — usually within 2–3 business days.'],
            ['Exchanges', 'Can I exchange a gift?', 'Gifts can be exchanged for another size or colour, or for store credit, within 30 days of the original delivery date.'],
            ['Payments', 'Which payment methods do you accept?', 'Visa, Mastercard, American Express, RuPay, UPI, net banking, major wallets and cash on delivery (a small handling fee applies to COD).'],
            ['Payments', 'Is my payment information secure?', 'All payments are processed over 256-bit encrypted connections by PCI-DSS compliant partners. We never store your card details.'],
            ['Products', 'How do I find my size?', 'Every product page links to our size guide with detailed measurements. If you are between sizes, we suggest sizing up for outerwear and down for knitwear.'],
            ['Products', 'Are your products authentic?', 'Every piece is designed by the house and made in our partner ateliers. Each order ships with a certificate of authenticity.'],
            ['Account', 'Do I need an account to order?', 'No — you can check out as a guest. An account lets you track orders, save addresses and keep a wishlist across devices.'],
            ['Account', 'How do I reset my password?', 'Use “Forgot password” on the sign-in page and we will email you a secure reset link.'],
            ['General', 'Do you have physical boutiques?', 'Yes. Our flagship is in Bengaluru with boutiques in Mumbai and New Delhi. See the store locator for hours and directions.'],
            ['General', 'How can I contact you?', 'Email care@maisonelan.com or call +91 80 4500 1200, Monday to Saturday, 10:00–19:00 IST.'],
        ];
        Faq::where('template', 'fashion')->delete();
        foreach ($faqs as $i => [$cat, $q, $a]) {
            Faq::create(['category' => $cat, 'question' => $q, 'answer' => $a, 'sort_order' => $i, 'template' => 'fashion']);
        }

        foreach ([
            ['name' => 'Bengaluru Flagship', 'address' => '12 Lavelle Road, Ashok Nagar', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '560001', 'phone' => '+91 80 4500 1200', 'email' => 'bengaluru@maisonelan.com', 'hours' => "Mon–Sat 10:30–20:00\nSun 11:00–18:00", 'lat' => 12.9716, 'lng' => 77.5946, 'image' => Media::unsplash('1445205170230-053b83016050', 1200, '&h=900'), 'maps_url' => 'https://maps.google.com/?q=Lavelle+Road+Bengaluru'],
            ['name' => 'Mumbai Boutique', 'address' => 'Kala Ghoda, Fort', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'postal_code' => '400001', 'phone' => '+91 22 4500 1300', 'email' => 'mumbai@maisonelan.com', 'hours' => "Mon–Sat 11:00–20:00\nSun 12:00–18:00", 'lat' => 18.9282, 'lng' => 72.8318, 'image' => Media::unsplash('1551232864-3f0890e580d9', 1200, '&h=900'), 'maps_url' => 'https://maps.google.com/?q=Kala+Ghoda+Mumbai'],
            ['name' => 'New Delhi Boutique', 'address' => 'The Chanakya, Chanakyapuri', 'city' => 'New Delhi', 'state' => 'Delhi', 'postal_code' => '110021', 'phone' => '+91 11 4500 1400', 'email' => 'delhi@maisonelan.com', 'hours' => "Mon–Sun 11:00–21:00", 'lat' => 28.5985, 'lng' => 77.1866, 'image' => Media::unsplash('1558769132-cb1aea458c5e', 1200, '&h=900'), 'maps_url' => 'https://maps.google.com/?q=The+Chanakya+New+Delhi'],
        ] as $i => $store) {
            Store::updateOrCreate(['name' => $store['name']], $store + ['sort_order' => $i]);
        }
    }
}
