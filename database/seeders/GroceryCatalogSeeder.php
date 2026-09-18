<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Support\Media;
use Illuminate\Database\Seeder;

/**
 * Starter catalogue for the Indian Grocery template. Every record is tagged
 * `template = grocery`, so it never appears in the Fashion storefront. Idempotent.
 */
class GroceryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $img = fn (string $id, int $w = 900, int $h = 900) => Media::unsplash($id, $w, "&h={$h}");

        $categories = [
            ['slug' => 'fruits-vegetables', 'name' => 'Fruits & Vegetables', 'tagline' => 'Farm-fresh, delivered daily', 'image' => $img('1550989460-0adf9ea622e2')],
            ['slug' => 'dairy-bread-eggs', 'name' => 'Dairy, Bread & Eggs', 'tagline' => 'Milk, butter, paneer and more', 'image' => $img('1550583724-b2692b85b150')],
            ['slug' => 'atta-rice-dal', 'name' => 'Atta, Rice & Dal', 'tagline' => 'Kitchen staples', 'image' => $img('1586201375761-83865001e31c')],
            ['slug' => 'oils-ghee-masalas', 'name' => 'Oils, Ghee & Masalas', 'tagline' => 'Everyday cooking essentials', 'image' => $img('1596040033229-a9821ebd058d')],
            ['slug' => 'snacks-namkeen', 'name' => 'Snacks & Namkeen', 'tagline' => 'Chips, bhujia and munchies', 'image' => $img('1566478989037-eec170784d0b')],
            ['slug' => 'biscuits-chocolates', 'name' => 'Biscuits & Chocolates', 'tagline' => 'Sweet treats', 'image' => $img('1558961363-fa8fdf82db35')],
            ['slug' => 'tea-coffee-beverages', 'name' => 'Tea, Coffee & Beverages', 'tagline' => 'Chai, coffee, juices and colas', 'image' => $img('1544787219-7f47ccb76574')],
            ['slug' => 'instant-frozen', 'name' => 'Instant & Frozen Food', 'tagline' => 'Ready in minutes', 'image' => $img('1612927601601-6638404737ce')],
            ['slug' => 'dry-fruits-nuts', 'name' => 'Dry Fruits & Nuts', 'tagline' => 'Almonds, cashews, raisins', 'image' => $img('1508061253366-f7da158b6d46')],
            ['slug' => 'personal-care', 'name' => 'Personal Care', 'tagline' => 'Bath, skin and oral care', 'image' => $img('1571781926291-c477ebfd024b')],
            ['slug' => 'household-cleaning', 'name' => 'Household & Cleaning', 'tagline' => 'Detergents and cleaners', 'image' => $img('1563453392212-326f5e854473')],
            ['slug' => 'baby-care', 'name' => 'Baby Care', 'tagline' => 'Diapers, wipes and food', 'image' => $img('1515488042361-ee00e0ddd4e4')],
        ];
        $cat = [];
        foreach ($categories as $i => $c) {
            $cat[$c['slug']] = Category::updateOrCreate(['slug' => $c['slug']], $c + [
                'sort_order' => $i,
                'description' => $c['tagline'],
                'template' => 'grocery',
                'is_active' => true,
                'show_in_menu' => true,
            ]);
        }

        // Sub-categories used by the category bar dropdowns and listing filters.
        $subs = [
            'fruits-vegetables' => ['Fresh Fruits' => 'fresh-fruits', 'Fresh Vegetables' => 'fresh-vegetables', 'Herbs & Leafy Greens' => 'herbs-leafy-greens', 'Exotic & Organic' => 'exotic-organic'],
            'dairy-bread-eggs' => ['Milk' => 'milk', 'Butter, Cheese & Paneer' => 'butter-cheese-paneer', 'Bread & Bakery' => 'bread-bakery', 'Eggs' => 'eggs'],
            'atta-rice-dal' => ['Atta & Flours' => 'atta-flours', 'Rice & Rice Products' => 'rice', 'Dals & Pulses' => 'dals-pulses'],
            'oils-ghee-masalas' => ['Edible Oils' => 'edible-oils', 'Ghee' => 'ghee', 'Masalas & Spices' => 'masalas-spices', 'Salt & Sugar' => 'salt-sugar'],
            'snacks-namkeen' => ['Chips & Crisps' => 'chips-crisps', 'Namkeen & Bhujia' => 'namkeen-bhujia'],
            'biscuits-chocolates' => ['Biscuits & Cookies' => 'biscuits-cookies', 'Chocolates' => 'chocolates'],
            'tea-coffee-beverages' => ['Tea' => 'tea', 'Coffee' => 'coffee', 'Juices & Cold Drinks' => 'juices-cold-drinks'],
            'instant-frozen' => ['Noodles & Pasta' => 'noodles-pasta', 'Frozen Snacks' => 'frozen-snacks', 'Ice Cream' => 'ice-cream'],
        ];
        foreach ($subs as $parentSlug => $children) {
            $j = 0;
            foreach ($children as $name => $slug) {
                $cat[$slug] = Category::updateOrCreate(['slug' => $slug], [
                    'name' => $name,
                    'parent_id' => $cat[$parentSlug]->id,
                    'sort_order' => $j++,
                    'template' => 'grocery',
                    'is_active' => true,
                    'show_in_menu' => true,
                ]);
            }
        }

        $veg = ['is_veg' => true];
        $products = [
            // Fruits & vegetables
            ['slug' => 'banana-robusta', 'name' => 'Banana Robusta', 'category' => 'fresh-fruits', 'brand' => 'Fresho', 'price' => 42, 'compare_at_price' => 55, 'sizes' => ['6 pcs'], 'images' => [$img('1571771894821-ce9b6c11b08e'), $img('1603833665858-e61d17a86224')], 'rating' => 4.5, 'review_count' => 1840, 'is_best_seller' => true, 'description' => 'Naturally ripened Robusta bananas — sweet, firm and perfect for lunchboxes and smoothies.', 'shelf_life' => '3–4 days', 'country_of_origin' => 'India', 'storage_instructions' => 'Store at room temperature. Refrigerate once ripe to slow ripening.', 'max_qty' => 5] + $veg,
            ['slug' => 'apple-shimla', 'name' => 'Apple Shimla', 'category' => 'fresh-fruits', 'brand' => 'Fresho', 'price' => 189, 'compare_at_price' => 240, 'sizes' => ['1 kg'], 'images' => [$img('1567306226416-28f0efdc88ce'), $img('1568702846914-96b305d2aaeb')], 'rating' => 4.4, 'review_count' => 960, 'is_best_seller' => true, 'description' => 'Crisp, juicy Shimla apples with a sweet-tart bite. Hand-sorted for size and colour.', 'shelf_life' => '7–10 days', 'country_of_origin' => 'India', 'storage_instructions' => 'Refrigerate to keep crisp.'] + $veg,
            ['slug' => 'tomato-hybrid', 'name' => 'Tomato Hybrid', 'category' => 'fresh-vegetables', 'brand' => 'Fresho', 'price' => 34, 'sizes' => ['500 g'], 'images' => [$img('1546094096-0df4bcaaa337'), $img('1592924357228-91a4daadcfea')], 'rating' => 4.3, 'review_count' => 2210, 'is_best_seller' => true, 'description' => 'Firm, bright red hybrid tomatoes for curries, salads and chutneys.', 'shelf_life' => '4–5 days', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'onion-red', 'name' => 'Onion (Red)', 'category' => 'fresh-vegetables', 'brand' => 'Fresho', 'price' => 39, 'compare_at_price' => 48, 'sizes' => ['1 kg'], 'images' => [$img('1618512496248-a07fe83aa8cb'), $img('1508747703725-719777637510')], 'rating' => 4.4, 'review_count' => 3120, 'is_best_seller' => true, 'description' => 'Medium-sized red onions with a sharp, sweet flavour. The base of every Indian kitchen.', 'shelf_life' => '2–3 weeks', 'country_of_origin' => 'India', 'storage_instructions' => 'Keep in a cool, dry, ventilated place.'] + $veg,
            ['slug' => 'potato', 'name' => 'Potato', 'category' => 'fresh-vegetables', 'brand' => 'Fresho', 'price' => 56, 'sizes' => ['2 kg'], 'images' => [$img('1518977676601-b53f82aba655')], 'rating' => 4.5, 'review_count' => 2780, 'description' => 'All-purpose potatoes — great for sabzi, fries and parathas.', 'shelf_life' => '2–3 weeks', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'carrot-orange', 'name' => 'Carrot (Orange)', 'category' => 'fresh-vegetables', 'brand' => 'Fresho', 'price' => 45, 'sizes' => ['500 g'], 'images' => [$img('1598170845058-32b9d6a5da37'), $img('1582515073490-39981397c445')], 'rating' => 4.4, 'review_count' => 640, 'is_new' => true, 'description' => 'Sweet, crunchy carrots for salads, juices and gajar ka halwa.', 'shelf_life' => '7 days', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'spinach-palak', 'name' => 'Spinach (Palak)', 'category' => 'herbs-leafy-greens', 'brand' => 'Fresho', 'price' => 22, 'sizes' => ['250 g'], 'images' => [$img('1576045057995-568f588f82fb')], 'rating' => 4.2, 'review_count' => 410, 'description' => 'Tender palak leaves, washed and bunched. Perfect for palak paneer and dal.', 'shelf_life' => '2 days', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'broccoli', 'name' => 'Broccoli', 'category' => 'exotic-organic', 'brand' => 'Fresho', 'price' => 79, 'compare_at_price' => 99, 'sizes' => ['1 pc (approx 350 g)'], 'images' => [$img('1615485290382-441e4d049cb5')], 'rating' => 4.3, 'review_count' => 320, 'is_new' => true, 'description' => 'Fresh green broccoli heads — steam, stir-fry or roast.', 'shelf_life' => '4–5 days', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'pomegranate', 'name' => 'Pomegranate', 'category' => 'fresh-fruits', 'brand' => 'Fresho', 'price' => 149, 'sizes' => ['2 pcs'], 'images' => [$img('1615485925600-97237c4fc1ec')], 'rating' => 4.6, 'review_count' => 512, 'description' => 'Ruby-red arils, sweet and juicy. Great for breakfast bowls and raita.', 'shelf_life' => '7–10 days', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'watermelon', 'name' => 'Watermelon', 'category' => 'fresh-fruits', 'brand' => 'Fresho', 'price' => 89, 'compare_at_price' => 120, 'sizes' => ['1 pc (2–3 kg)'], 'images' => [$img('1587049352846-4a222e784d38'), $img('1595475207225-428b62bda831')], 'rating' => 4.5, 'review_count' => 780, 'description' => 'Sweet, seedless-variety watermelon, chilled and ready to slice.', 'shelf_life' => '5 days', 'country_of_origin' => 'India', 'max_qty' => 2] + $veg,
            ['slug' => 'strawberry', 'name' => 'Strawberry', 'category' => 'exotic-organic', 'brand' => 'Fresho', 'price' => 119, 'compare_at_price' => 160, 'sizes' => ['200 g'], 'images' => [$img('1601004890684-d8cbf643f5f2'), $img('1588165171080-c89acfa5ee83')], 'rating' => 4.4, 'review_count' => 290, 'is_new' => true, 'description' => 'Mahabaleshwar strawberries — bright, fragrant and sweet.', 'shelf_life' => '2 days', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'alphonso-mango', 'name' => 'Alphonso Mango', 'category' => 'fresh-fruits', 'brand' => 'Fresho', 'price' => 499, 'compare_at_price' => 599, 'sizes' => ['1 dozen'], 'images' => [$img('1601493700631-2b16ec4b4716')], 'rating' => 4.8, 'review_count' => 1130, 'is_best_seller' => true, 'description' => 'Ratnagiri Alphonso — the king of mangoes. Naturally ripened, no carbide.', 'shelf_life' => '5–6 days', 'country_of_origin' => 'India', 'max_qty' => 3] + $veg,
            ['slug' => 'kiwi-green', 'name' => 'Kiwi (Green)', 'category' => 'exotic-organic', 'brand' => 'Fresho', 'price' => 99, 'sizes' => ['3 pcs'], 'images' => [$img('1618897996318-5a901fa6ca71')], 'rating' => 4.3, 'review_count' => 210, 'description' => 'Tangy-sweet green kiwis, rich in vitamin C.', 'shelf_life' => '7 days', 'country_of_origin' => 'New Zealand'] + $veg,
            ['slug' => 'avocado-hass', 'name' => 'Avocado (Hass)', 'category' => 'exotic-organic', 'brand' => 'Fresho', 'price' => 159, 'sizes' => ['2 pcs'], 'images' => [$img('1590005024862-6b67679a29fb')], 'rating' => 4.2, 'review_count' => 150, 'is_new' => true, 'description' => 'Creamy Hass avocados, ready to eat in 1–2 days.', 'shelf_life' => '3–4 days', 'country_of_origin' => 'Peru'] + $veg,
            ['slug' => 'capsicum-mixed', 'name' => 'Capsicum (Red, Yellow & Green)', 'category' => 'fresh-vegetables', 'brand' => 'Fresho', 'price' => 89, 'sizes' => ['3 pcs'], 'images' => [$img('1601648764658-cf37e8c89b70')], 'rating' => 4.4, 'review_count' => 380, 'description' => 'A trio of crunchy bell peppers for stir-fries, pizzas and salads.', 'shelf_life' => '5–7 days', 'country_of_origin' => 'India'] + $veg,

            // Dairy, bread & eggs
            ['slug' => 'amul-taaza-toned-milk', 'name' => 'Amul Taaza Toned Milk', 'category' => 'milk', 'brand' => 'Amul', 'price' => 27, 'sizes' => ['500 ml'], 'images' => [$img('1563636619-e9143da7973b')], 'rating' => 4.7, 'review_count' => 8900, 'is_best_seller' => true, 'description' => 'Homogenised toned milk, pasteurised for daily use. 3% fat.', 'shelf_life' => '2 days', 'country_of_origin' => 'India', 'storage_instructions' => 'Keep refrigerated at 4°C.', 'ingredients' => 'Toned milk', 'max_qty' => 10] + $veg,
            ['slug' => 'amul-butter', 'name' => 'Amul Butter (Pasteurised)', 'category' => 'butter-cheese-paneer', 'brand' => 'Amul', 'price' => 275, 'sizes' => ['500 g'], 'images' => [$img('1589985270826-4b7bb135bc9d')], 'rating' => 4.8, 'review_count' => 5100, 'is_best_seller' => true, 'description' => 'The utterly butterly classic. Salted, creamy and made from fresh cream.', 'shelf_life' => '9 months', 'country_of_origin' => 'India', 'storage_instructions' => 'Refrigerate.', 'ingredients' => 'Milk fat, common salt, permitted natural colour (annatto)'] + $veg,
            ['slug' => 'farm-fresh-eggs', 'name' => 'Farm Fresh White Eggs', 'category' => 'eggs', 'brand' => 'Fresho', 'price' => 84, 'compare_at_price' => 96, 'sizes' => ['12 pcs'], 'images' => [$img('1506976785307-8732e854ad03'), $img('1587486913049-53fc88980cfc')], 'rating' => 4.6, 'review_count' => 3400, 'is_best_seller' => true, 'is_veg' => false, 'description' => 'Farm-fresh white eggs, cleaned and packed within 24 hours of laying.', 'shelf_life' => '2 weeks', 'country_of_origin' => 'India', 'storage_instructions' => 'Refrigerate.'],
            ['slug' => 'multigrain-bread', 'name' => 'Multigrain Bread', 'category' => 'bread-bakery', 'brand' => 'Britannia', 'price' => 55, 'sizes' => ['400 g'], 'images' => [$img('1509440159596-0249088772ff')], 'rating' => 4.4, 'review_count' => 1200, 'description' => 'Soft sliced bread with seven grains and seeds. No trans fat.', 'shelf_life' => '4 days', 'country_of_origin' => 'India', 'ingredients' => 'Wheat flour, multigrain mix (oats, ragi, jowar, bajra, flax, sunflower, pumpkin seeds), sugar, yeast, salt'] + $veg,
            ['slug' => 'malai-paneer', 'name' => 'Amul Malai Paneer', 'category' => 'butter-cheese-paneer', 'brand' => 'Amul', 'price' => 92, 'sizes' => ['200 g'], 'images' => [$img('1604329760661-e71dc83f8f26')], 'rating' => 4.6, 'review_count' => 2100, 'description' => 'Soft, creamy paneer that stays tender in gravies and on the grill.', 'shelf_life' => '15 days', 'country_of_origin' => 'India', 'storage_instructions' => 'Refrigerate. Consume within 2 days of opening.', 'ingredients' => 'Milk solids, citric acid'] + $veg,

            // Atta, rice & dal
            ['slug' => 'aashirvaad-atta', 'name' => 'Aashirvaad Whole Wheat Atta', 'category' => 'atta-flours', 'brand' => 'Aashirvaad', 'price' => 259, 'compare_at_price' => 285, 'sizes' => ['5 kg'], 'images' => [$img('1574323347407-f5e1ad6d020b')], 'rating' => 4.7, 'review_count' => 12400, 'is_best_seller' => true, 'description' => '100% whole wheat atta with the goodness of choicest grains for soft, fluffy rotis.', 'shelf_life' => '3 months', 'country_of_origin' => 'India', 'ingredients' => 'Whole wheat'] + $veg,
            ['slug' => 'india-gate-basmati', 'name' => 'India Gate Classic Basmati Rice', 'category' => 'rice', 'brand' => 'India Gate', 'price' => 649, 'compare_at_price' => 725, 'sizes' => ['5 kg'], 'images' => [$img('1586201375761-83865001e31c')], 'rating' => 4.6, 'review_count' => 6800, 'is_best_seller' => true, 'description' => 'Aged, extra-long grain basmati that cooks up fluffy and fragrant.', 'shelf_life' => '12 months', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'toor-dal', 'name' => 'Toor Dal (Arhar)', 'category' => 'dals-pulses', 'brand' => 'Tata Sampann', 'price' => 168, 'sizes' => ['1 kg'], 'images' => [$img('1515543904379-3d757afe72e4')], 'rating' => 4.5, 'review_count' => 3900, 'description' => 'Unpolished toor dal with natural protein and taste retained.', 'shelf_life' => '6 months', 'country_of_origin' => 'India'] + $veg,

            // Oils, ghee & masalas
            ['slug' => 'fortune-sunflower-oil', 'name' => 'Fortune Sunlite Refined Sunflower Oil', 'category' => 'edible-oils', 'brand' => 'Fortune', 'price' => 145, 'compare_at_price' => 165, 'sizes' => ['1 L'], 'images' => [$img('1474979266404-7eaacbcd87c5')], 'rating' => 4.5, 'review_count' => 7100, 'is_best_seller' => true, 'description' => 'Light, healthy sunflower oil that keeps food crisp and non-greasy.', 'shelf_life' => '12 months', 'country_of_origin' => 'India', 'ingredients' => 'Refined sunflower oil, vitamins A & D'] + $veg,
            ['slug' => 'amul-pure-ghee', 'name' => 'Amul Pure Ghee', 'category' => 'ghee', 'brand' => 'Amul', 'price' => 615, 'sizes' => ['1 L'], 'images' => [$img('1589985270826-4b7bb135bc9d', 900, 900).'&crop=left'], 'rating' => 4.8, 'review_count' => 5400, 'description' => 'Rich, aromatic cow ghee made from fresh cream. Granular texture.', 'shelf_life' => '9 months', 'country_of_origin' => 'India', 'ingredients' => 'Milk fat'] + $veg,
            ['slug' => 'mdh-garam-masala', 'name' => 'MDH Garam Masala', 'category' => 'masalas-spices', 'brand' => 'MDH', 'price' => 78, 'sizes' => ['100 g'], 'images' => [$img('1596040033229-a9821ebd058d')], 'rating' => 4.6, 'review_count' => 4200, 'is_best_seller' => true, 'description' => 'The classic blend of cardamom, cinnamon, cloves and black pepper.', 'shelf_life' => '12 months', 'country_of_origin' => 'India', 'ingredients' => 'Coriander, black pepper, cumin, cardamom, cinnamon, cloves, nutmeg, bay leaf'] + $veg,
            ['slug' => 'everest-turmeric', 'name' => 'Everest Turmeric Powder', 'category' => 'masalas-spices', 'brand' => 'Everest', 'price' => 62, 'sizes' => ['200 g'], 'images' => [$img('1615485500704-8e990f9900f7')], 'rating' => 4.5, 'review_count' => 2600, 'description' => 'High-curcumin haldi with a deep colour and earthy aroma.', 'shelf_life' => '12 months', 'country_of_origin' => 'India', 'ingredients' => 'Turmeric'] + $veg,
            ['slug' => 'tata-salt', 'name' => 'Tata Salt Iodised', 'category' => 'salt-sugar', 'brand' => 'Tata', 'price' => 28, 'sizes' => ['1 kg'], 'images' => [$img('1518110925495-5fe2fda0442c')], 'rating' => 4.7, 'review_count' => 15600, 'is_best_seller' => true, 'description' => 'Desh ka namak — vacuum-evaporated iodised salt.', 'shelf_life' => '24 months', 'country_of_origin' => 'India', 'ingredients' => 'Salt, potassium iodate'] + $veg,

            // Snacks & namkeen
            ['slug' => 'lays-magic-masala', 'name' => "Lay's India's Magic Masala", 'category' => 'chips-crisps', 'brand' => "Lay's", 'price' => 20, 'sizes' => ['52 g'], 'images' => [$img('1566478989037-eec170784d0b')], 'rating' => 4.5, 'review_count' => 9800, 'is_best_seller' => true, 'description' => 'Crispy potato chips with the iconic tangy masala seasoning.', 'shelf_life' => '4 months', 'country_of_origin' => 'India', 'max_qty' => 10] + $veg,
            ['slug' => 'nachos-cheese', 'name' => 'Cornitos Nacho Crisps — Cheese & Herbs', 'category' => 'chips-crisps', 'brand' => 'Cornitos', 'price' => 60, 'compare_at_price' => 75, 'sizes' => ['150 g'], 'images' => [$img('1574672280600-4accfa5b6f98')], 'rating' => 4.3, 'review_count' => 760, 'is_new' => true, 'description' => 'Crunchy corn nachos dusted with cheese and herbs.', 'shelf_life' => '6 months', 'country_of_origin' => 'India'] + $veg,

            // Biscuits & chocolates
            ['slug' => 'parle-g', 'name' => 'Parle-G Original Gluco Biscuits', 'category' => 'biscuits-cookies', 'brand' => 'Parle', 'price' => 85, 'compare_at_price' => 100, 'sizes' => ['800 g'], 'images' => [$img('1558961363-fa8fdf82db35')], 'rating' => 4.7, 'review_count' => 21000, 'is_best_seller' => true, 'description' => 'India’s favourite glucose biscuit — perfect with chai.', 'shelf_life' => '6 months', 'country_of_origin' => 'India', 'ingredients' => 'Wheat flour, sugar, edible vegetable oil, invert syrup, milk solids, salt, leavening agents'] + $veg,
            ['slug' => 'oreo-vanilla', 'name' => 'Cadbury Oreo Vanilla Crème', 'category' => 'biscuits-cookies', 'brand' => 'Cadbury', 'price' => 30, 'sizes' => ['120 g'], 'images' => [$img('1499636136210-6f4ee915583e')], 'rating' => 4.6, 'review_count' => 5300, 'description' => 'Chocolatey sandwich biscuits with vanilla crème. Twist, lick, dunk.', 'shelf_life' => '9 months', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'dairy-milk-silk', 'name' => 'Cadbury Dairy Milk Silk', 'category' => 'chocolates', 'brand' => 'Cadbury', 'price' => 95, 'compare_at_price' => 110, 'sizes' => ['150 g'], 'images' => [$img('1511381939415-e44015466834'), $img('1610450949065-1f2841536c88')], 'rating' => 4.7, 'review_count' => 8800, 'is_best_seller' => true, 'description' => 'Smooth, silky milk chocolate that melts in your mouth.', 'shelf_life' => '9 months', 'country_of_origin' => 'India', 'storage_instructions' => 'Store in a cool, dry place away from sunlight.'] + $veg,
            ['slug' => 'assorted-chocolates', 'name' => 'Assorted Chocolate Bars Pack', 'category' => 'chocolates', 'brand' => 'Mars', 'price' => 249, 'compare_at_price' => 299, 'sizes' => ['10 × 25 g'], 'images' => [$img('1621939514649-280e2ee25f60'), $img('1599599810769-bcde5a160d32')], 'rating' => 4.5, 'review_count' => 640, 'is_new' => true, 'description' => 'Snickers, Bounty, Twix and more — a party pack of minis.', 'shelf_life' => '9 months', 'country_of_origin' => 'UAE'] + $veg,

            // Tea, coffee & beverages
            ['slug' => 'tata-tea-gold', 'name' => 'Tata Tea Gold', 'category' => 'tea', 'brand' => 'Tata Tea', 'price' => 285, 'compare_at_price' => 320, 'sizes' => ['500 g'], 'images' => [$img('1544787219-7f47ccb76574')], 'rating' => 4.6, 'review_count' => 7400, 'is_best_seller' => true, 'description' => 'Assam CTC with 15% gently rolled long leaves for a rich, aromatic cup.', 'shelf_life' => '12 months', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'nescafe-classic', 'name' => 'Nescafé Classic Instant Coffee', 'category' => 'coffee', 'brand' => 'Nescafé', 'price' => 455, 'sizes' => ['200 g'], 'images' => [$img('1497515114629-f71d768fd07c'), $img('1610632380989-680fe40816c6')], 'rating' => 4.6, 'review_count' => 6100, 'description' => '100% pure coffee. Rich aroma, bold taste — the classic morning cup.', 'shelf_life' => '18 months', 'country_of_origin' => 'India', 'ingredients' => 'Instant coffee'] + $veg,
            ['slug' => 'coca-cola-750', 'name' => 'Coca-Cola Original', 'category' => 'juices-cold-drinks', 'brand' => 'Coca-Cola', 'price' => 40, 'sizes' => ['750 ml'], 'images' => [$img('1554866585-cd94860890b7')], 'rating' => 4.5, 'review_count' => 4300, 'description' => 'Ice-cold refreshment. Serve chilled.', 'shelf_life' => '6 months', 'country_of_origin' => 'India', 'max_qty' => 12] + $veg,
            ['slug' => 'real-orange-juice', 'name' => 'Real Fruit Power Orange Juice', 'category' => 'juices-cold-drinks', 'brand' => 'Real', 'price' => 115, 'compare_at_price' => 130, 'sizes' => ['1 L'], 'images' => [$img('1600271886742-f049cd451bba'), $img('1613478223719-2ab802602423')], 'rating' => 4.4, 'review_count' => 1900, 'description' => 'Orange juice with real fruit and no added preservatives.', 'shelf_life' => '6 months', 'country_of_origin' => 'India', 'storage_instructions' => 'Refrigerate after opening; consume within 3 days.'] + $veg,
            ['slug' => 'green-tea-lemon', 'name' => 'Tetley Green Tea — Lemon & Honey', 'category' => 'tea', 'brand' => 'Tetley', 'price' => 199, 'sizes' => ['25 bags'], 'images' => [$img('1597318181409-cf64d0b5d8a2')], 'rating' => 4.3, 'review_count' => 980, 'is_new' => true, 'description' => 'Light green tea with a zesty lemon and honey note.', 'shelf_life' => '18 months', 'country_of_origin' => 'India'] + $veg,

            // Instant & frozen
            ['slug' => 'maggi-masala-noodles', 'name' => 'Maggi 2-Minute Masala Noodles', 'category' => 'noodles-pasta', 'brand' => 'Maggi', 'price' => 168, 'compare_at_price' => 180, 'sizes' => ['12 × 70 g'], 'images' => [$img('1612927601601-6638404737ce'), $img('1585032226651-759b368d7246')], 'rating' => 4.7, 'review_count' => 18700, 'is_best_seller' => true, 'description' => 'The 2-minute classic with the iconic Tastemaker. Family pack.', 'shelf_life' => '9 months', 'country_of_origin' => 'India'] + $veg,
            ['slug' => 'frozen-punjabi-samosa', 'name' => "Haldiram's Frozen Punjabi Samosa", 'category' => 'frozen-snacks', 'brand' => "Haldiram's", 'price' => 145, 'sizes' => ['400 g (8 pcs)'], 'images' => [$img('1601050690597-df0568f70950')], 'rating' => 4.4, 'review_count' => 1300, 'description' => 'Crispy samosas with a spiced potato and pea filling. Fry or air-fry from frozen.', 'shelf_life' => '12 months (frozen)', 'country_of_origin' => 'India', 'storage_instructions' => 'Keep frozen at -18°C.'] + $veg,
            ['slug' => 'chocolate-ice-cream-tub', 'name' => 'Amul Chocolate Ice Cream Tub', 'category' => 'ice-cream', 'brand' => 'Amul', 'price' => 199, 'compare_at_price' => 240, 'sizes' => ['1 L'], 'images' => [$img('1580915411954-282cb1b0d780')], 'rating' => 4.5, 'review_count' => 2300, 'description' => 'Real milk ice cream with rich Belgian-style chocolate.', 'shelf_life' => '9 months (frozen)', 'country_of_origin' => 'India', 'storage_instructions' => 'Keep frozen.'] + $veg,
            ['slug' => 'frozen-margherita-pizza', 'name' => 'Frozen Margherita Pizza', 'category' => 'frozen-snacks', 'brand' => 'Chef Special', 'price' => 219, 'sizes' => ['320 g'], 'images' => [$img('1585238342024-78d387f4a707'), $img('1600628421066-f6bda6a7b976')], 'rating' => 4.2, 'review_count' => 410, 'is_new' => true, 'description' => 'Thin-crust pizza with mozzarella and tomato-basil sauce. Oven-ready in 12 minutes.', 'shelf_life' => '6 months (frozen)', 'country_of_origin' => 'India'] + $veg,

            // Dry fruits & nuts
            ['slug' => 'california-almonds', 'name' => 'California Almonds', 'category' => 'dry-fruits-nuts', 'brand' => 'Happilo', 'price' => 449, 'compare_at_price' => 599, 'sizes' => ['500 g'], 'images' => [$img('1508061253366-f7da158b6d46'), $img('1608797178974-15b35a64ede9')], 'rating' => 4.6, 'review_count' => 3200, 'is_best_seller' => true, 'description' => 'Premium whole almonds — crunchy, wholesome and rich in vitamin E.', 'shelf_life' => '12 months', 'country_of_origin' => 'USA'] + $veg,

            // Personal care
            ['slug' => 'gentle-face-wash', 'name' => 'Himalaya Purifying Neem Face Wash', 'category' => 'personal-care', 'brand' => 'Himalaya', 'price' => 165, 'compare_at_price' => 190, 'sizes' => ['200 ml'], 'images' => [$img('1556228578-8c89e6adf883'), $img('1571781926291-c477ebfd024b')], 'rating' => 4.4, 'review_count' => 2900, 'description' => 'Soap-free herbal face wash with neem and turmeric for clear skin.', 'shelf_life' => '36 months', 'country_of_origin' => 'India'],
            ['slug' => 'bamboo-toothbrush-pack', 'name' => 'Colgate Bamboo Charcoal Toothbrush (Pack of 4)', 'category' => 'personal-care', 'brand' => 'Colgate', 'price' => 199, 'sizes' => ['4 pcs'], 'images' => [$img('1607613009820-a29f7bb81c04')], 'rating' => 4.3, 'review_count' => 870, 'is_new' => true, 'description' => 'Soft charcoal-infused bristles on a biodegradable bamboo handle.', 'country_of_origin' => 'India'],
            ['slug' => 'multivitamin-tablets', 'name' => 'Daily Multivitamin Tablets', 'category' => 'personal-care', 'brand' => 'HealthKart', 'price' => 349, 'compare_at_price' => 449, 'sizes' => ['60 tablets'], 'images' => [$img('1584308666744-24d5c474f2ae')], 'rating' => 4.2, 'review_count' => 640, 'description' => 'One-a-day multivitamin with 24 vitamins and minerals.', 'shelf_life' => '24 months', 'country_of_origin' => 'India'],

            // Household & cleaning
            ['slug' => 'surf-excel-matic', 'name' => 'Surf Excel Matic Front Load Liquid', 'category' => 'household-cleaning', 'brand' => 'Surf Excel', 'price' => 399, 'compare_at_price' => 460, 'sizes' => ['2 L'], 'images' => [$img('1563453392212-326f5e854473')], 'rating' => 4.6, 'review_count' => 5100, 'is_best_seller' => true, 'description' => 'Liquid detergent for front-load machines. Removes tough stains in one wash.', 'shelf_life' => '24 months', 'country_of_origin' => 'India'],
            ['slug' => 'harpic-bathroom-cleaner', 'name' => 'Harpic Bathroom Cleaner — Lemon', 'category' => 'household-cleaning', 'brand' => 'Harpic', 'price' => 189, 'sizes' => ['1 L'], 'images' => [$img('1584622650111-993a426fbf0a')], 'rating' => 4.5, 'review_count' => 2200, 'description' => 'Removes tough stains and limescale from tiles, taps and floors.', 'shelf_life' => '24 months', 'country_of_origin' => 'India'],

            // Baby care
            ['slug' => 'pampers-diapers-m', 'name' => 'Pampers All Round Protection Pants — Medium', 'category' => 'baby-care', 'brand' => 'Pampers', 'price' => 899, 'compare_at_price' => 1049, 'sizes' => ['58 pcs'], 'images' => [$img('1515488042361-ee00e0ddd4e4'), $img('1519689680058-324335c77eba')], 'rating' => 4.7, 'review_count' => 7600, 'is_best_seller' => true, 'description' => 'Up to 12 hours of dryness with a lotion-infused, breathable top layer.', 'country_of_origin' => 'India'],
        ];

        $models = [];
        foreach ($products as $i => $data) {
            $categorySlug = $data['category'];
            unset($data['category']);
            $models[$data['slug']] = Product::updateOrCreate(['slug' => $data['slug']], $data + [
                'category_id' => $cat[$categorySlug]->id,
                'sort_order' => $i,
                'sku' => 'GR-'.str_pad((string) ($i + 2001), 4, '0', STR_PAD_LEFT),
                'colors' => [],
                'stock' => 120,
                'is_active' => true,
                'template' => 'grocery',
            ]);
        }

        $collections = [
            ['slug' => 'breakfast-essentials', 'name' => 'Breakfast Essentials', 'description' => 'Milk, bread, eggs, butter and everything for a quick morning.', 'image' => $img('1550583724-b2692b85b150', 1200, 1500), 'is_featured' => true, 'products' => ['amul-taaza-toned-milk', 'multigrain-bread', 'farm-fresh-eggs', 'amul-butter', 'tata-tea-gold', 'banana-robusta']],
            ['slug' => 'monthly-grocery-list', 'name' => 'Monthly Grocery List', 'description' => 'The big-pack staples every Indian kitchen restocks each month.', 'image' => $img('1586201375761-83865001e31c', 1200, 1500), 'products' => ['aashirvaad-atta', 'india-gate-basmati', 'toor-dal', 'fortune-sunflower-oil', 'amul-pure-ghee', 'tata-salt', 'mdh-garam-masala', 'everest-turmeric']],
            ['slug' => 'movie-night', 'name' => 'Movie Night', 'description' => 'Chips, chocolates, colas and pizza for the couch.', 'image' => $img('1566478989037-eec170784d0b', 1200, 1500), 'products' => ['lays-magic-masala', 'nachos-cheese', 'dairy-milk-silk', 'assorted-chocolates', 'coca-cola-750', 'frozen-margherita-pizza', 'chocolate-ice-cream-tub']],
            ['slug' => 'healthy-picks', 'name' => 'Healthy Picks', 'description' => 'Fresh fruit, greens, nuts and green tea for a lighter week.', 'image' => $img('1615485290382-441e4d049cb5', 1200, 1500), 'products' => ['california-almonds', 'green-tea-lemon', 'broccoli', 'spinach-palak', 'kiwi-green', 'avocado-hass', 'pomegranate']],
        ];
        foreach ($collections as $i => $data) {
            $slugs = $data['products'];
            unset($data['products']);
            $collection = Collection::updateOrCreate(['slug' => $data['slug']], $data + ['sort_order' => 100 + $i, 'is_active' => true, 'template' => 'grocery']);
            $sync = [];
            foreach ($slugs as $k => $slug) {
                $sync[$models[$slug]->id] = ['sort_order' => $k];
            }
            $collection->products()->sync($sync);
        }
    }
}
