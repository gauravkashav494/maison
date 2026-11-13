<?php

namespace Database\Seeders;

use App\Clients\ClientDatabase;
use App\Models\Address;
use App\Models\Category;
use App\Models\Client;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Support\Media;
use App\Support\ReviewModeration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for the Ethnic Fashion Store client: an Indian ethnic-wear catalogue, customers with
 * addresses, coupons, reviews and ~45 orders spread over the last 90 days in every status.
 *
 *   php artisan db:seed --class=EthnicFashionDemoSeeder
 *
 * Safe to re-run: catalogue rows are matched by slug, customers by email, and the demo orders
 * (numbers starting "EF-") are replaced. Randomness is seeded, so every run gives the same data.
 */
class EthnicFashionDemoSeeder extends Seeder
{
    private const SUBDOMAIN = 'ethnic-fashion-store';

    private const TEMPLATE = 'fashion';

    private int $clientId;

    public function run(): void
    {
        $client = Client::query()->where('subdomain', self::SUBDOMAIN)->firstOrFail();
        $this->clientId = (int) $client->id;
        mt_srand(20261113);

        app(ClientDatabase::class)->forClient($client, function () {
            $products = $this->catalogue();
            $this->coupons();
            $customers = $this->customers();
            // Orders first, so a review can come from someone who actually received the product.
            $this->orders($products, $customers);
            $this->reviews($products, $customers);
        });
    }

    /** @return array<string,Product> */
    private function catalogue(): array
    {
        $img = fn (string $id) => Media::unsplash($id, 900, '&h=1200');
        $wide = fn (string $id) => Media::unsplash($id, 1400, '&h=1750');

        // Every photo id below was checked by eye to match its product.
        $ph = [
            'saree_purple' => '1610030469983-98e550d6193c', 'saree_red' => '1617627143750-d86bc21e42bb',
            'saree_mint' => '1609748340041-f5d61e061ebc', 'saree_black' => '1610030469668-8e9f641aaf27',
            'kurta_green' => '1597983073493-88cd35cf93b0', 'kurta_maroon' => '1585487000160-6ebcfceb0d03',
            'anarkali_white' => '1583391733956-3750e0ff4e8b', 'gown_red' => '1595777457583-95e059d581b8',
            'gown_floral' => '1622122201714-77da0ca8e5d2', 'drape_red' => '1612336307429-8a898d10e223',
            'maxi_teal' => '1609357605129-26f69add5d6e', 'men_tux' => '1600091166971-7f9faad6c1e2',
            'men_navy' => '1617137968427-85924c800a22', 'men_shirts' => '1602810318383-e386cc2a3ccf',
            'men_shibori' => '1626497764746-6dc36546b388', 'bandhani' => '1606293926075-69a00dbfde81',
            'ring' => '1614252369475-531eba835eb1', 'necklace' => '1599643478518-a784e5dc4c8f',
            'earrings' => '1630019852942-f89202989a59', 'bangle' => '1611591437281-460bfbe1220a',
            'pendant' => '1588444837495-c6cfeb53f32d', 'rack' => '1603400521630-9f2de124b33b',
            'tee_white' => '1521572163474-6864f9cf17ab', 'tee_black' => '1618354691373-d851c5c3a990',
        ];

        $categories = [
            ['slug' => 'sarees', 'name' => 'Sarees', 'tagline' => 'Handwoven silks and everyday drapes', 'photo' => 'saree_red'],
            ['slug' => 'kurta-sets', 'name' => 'Kurta Sets', 'tagline' => 'Easy co-ords for every day and every festival', 'photo' => 'kurta_green'],
            ['slug' => 'anarkalis-gowns', 'name' => 'Anarkalis & Gowns', 'tagline' => 'Flared silhouettes for celebrations', 'photo' => 'anarkali_white'],
            ['slug' => 'indo-western', 'name' => 'Indo-Western', 'tagline' => 'Where tradition meets the everyday', 'photo' => 'maxi_teal'],
            ['slug' => 'menswear', 'name' => 'Menswear', 'tagline' => 'Bandhgalas, suits and festive shirts', 'photo' => 'men_navy'],
            ['slug' => 'dupattas', 'name' => 'Dupattas', 'tagline' => 'Bandhani, leheriya and zari edges', 'photo' => 'bandhani'],
            ['slug' => 'jewellery', 'name' => 'Jewellery', 'tagline' => 'Temple gold, kundan and silver', 'photo' => 'necklace'],
        ];
        $cat = [];
        foreach ($categories as $i => $c) {
            $cat[$c['slug']] = Category::withTrashed()->updateOrCreate(['slug' => $c['slug']], [
                'name' => $c['name'], 'tagline' => $c['tagline'], 'description' => $c['tagline'],
                'image' => $wide($ph[$c['photo']]), 'is_active' => true, 'show_in_menu' => true,
                'sort_order' => $i, 'template' => self::TEMPLATE, 'client_id' => $this->clientId, 'deleted_at' => null,
            ]);
        }

        $women = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        $men = ['38', '40', '42', '44', '46'];
        $free = ['Free size'];
        $one = ['One size'];
        $c = fn (string $name, string $hex) => ['name' => $name, 'hex' => $hex];

        // [slug, name, category, price, compare_at, photos, colours, sizes, flags, material, description]
        $rows = [
            ['banarasi-silk-saree-purple', 'Banarasi Silk Saree — Royal Purple', 'sarees', 18900, null, ['saree_purple', 'saree_black'], [$c('Purple', '#4B2A63')], $free, ['best'], 'Pure silk', 'Handwoven in Varanasi with a gold zari border and a rich, heavy pallu. Comes with an unstitched blouse piece.'],
            ['kanjivaram-bridal-saree', 'Kanjivaram Bridal Silk Saree', 'sarees', 34500, 38900, ['saree_red', 'saree_purple'], [$c('Red', '#B0182A'), $c('Magenta', '#B2236E')], $free, ['best', 'new'], 'Mulberry silk', 'Temple-border Kanjivaram woven with pure zari: the classic South Indian bridal drape.'],
            ['tissue-silk-saree-mint', 'Tissue Silk Saree — Mint', 'sarees', 12400, null, ['saree_mint', 'saree_purple'], [$c('Mint', '#B9D3B6')], $free, ['new'], 'Tissue silk', 'Light, lustrous tissue silk with woven buttis and a contrast olive blouse piece.'],
            ['chanderi-saree-charcoal', 'Chanderi Saree — Charcoal & Silver', 'sarees', 8900, 10900, ['saree_black', 'saree_mint'], [$c('Charcoal', '#2B2B2E')], $free, [], 'Chanderi cotton-silk', 'Sheer Chanderi with silver zari stripes. Easy to drape and comfortable all day.'],
            ['emerald-printed-kurta-set', 'Emerald Printed Kurta Set', 'kurta-sets', 4800, null, ['kurta_green', 'kurta_maroon'], [$c('Emerald', '#1E8C55')], $women, ['best'], 'Cotton', 'Straight kurta with block-print yoke, matching palazzo and a soft mul dupatta.'],
            ['maroon-velvet-kurta-dress', 'Maroon Velvet Kurta Dress', 'kurta-sets', 5600, 6900, ['kurta_maroon', 'kurta_green'], [$c('Maroon', '#6E1E33')], $women, [], 'Cotton velvet', 'A gathered kurta dress in fine-wale velvet with a band collar and pearl buttons.'],
            ['ivory-chikankari-anarkali', 'Ivory Chikankari Anarkali Set', 'anarkalis-gowns', 11900, null, ['anarkali_white', 'gown_red'], [$c('Ivory', '#EFE9DC')], $women, ['best', 'new'], 'Georgette', 'Hand-embroidered Lucknowi chikankari on a floor-length anarkali, with churidar and dupatta.'],
            ['scarlet-flared-anarkali-gown', 'Scarlet Flared Anarkali Gown', 'anarkalis-gowns', 9800, null, ['gown_red', 'anarkali_white'], [$c('Scarlet', '#C4161C')], $women, [], 'Crêpe', 'A sweeping 24-kali gown with a fitted bodice. Made for sangeet nights.'],
            ['floral-printed-lehenga-gown', 'Floral Printed Lehenga Gown', 'anarkalis-gowns', 14200, 16500, ['gown_floral', 'gown_red'], [$c('Rose', '#C8476E')], $women, ['new'], 'Satin', 'Halter-neck lehenga gown with an oversized rose print and a full, layered skirt.'],
            ['red-pre-draped-saree-gown', 'Red Pre-Draped Saree Gown', 'indo-western', 10500, null, ['drape_red', 'gown_red'], [$c('Red', '#B0182A')], $women, [], 'Crêpe georgette', 'The ease of a gown with the drape of a saree: sewn pleats and a structured high neck.'],
            ['teal-georgette-maxi', 'Teal Georgette Wrap Maxi', 'indo-western', 5200, null, ['maxi_teal', 'kurta_green'], [$c('Teal', '#2E8B7A')], $women, ['new'], 'Georgette', 'A flowing wrap maxi with balloon sleeves and a tiered hem.'],
            ['checked-bandhgala-tuxedo', 'Checked Bandhgala Tuxedo', 'menswear', 22500, null, ['men_tux', 'men_navy'], [$c('Slate', '#56606B')], $men, ['best'], 'Wool blend', 'A Windowpane-check tuxedo jacket, cut slim, with satin lapels. Pairs with a bow tie or a pocket square.'],
            ['navy-jodhpuri-suit', 'Navy Jodhpuri Suit', 'menswear', 19800, 23900, ['men_navy', 'men_tux'], [$c('Navy', '#1F2A44')], $men, [], 'Terry rayon', 'Two-piece suit in a deep navy, half-canvassed for a sharp, structured fit.'],
            ['cotton-festive-shirt', 'Cotton Festive Shirt', 'menswear', 2900, null, ['men_shirts', 'men_shibori'], [$c('Wine', '#5A1F38'), $c('White', '#F7F5F0'), $c('Indigo', '#2E3B5E')], $men, ['best'], 'Cotton poplin', 'Crisp cotton shirt with a hidden placket. Works under a Nehru jacket or on its own.'],
            ['shibori-dyed-shirt', 'Shibori Dyed Cotton Shirt', 'menswear', 3400, null, ['men_shibori', 'men_shirts'], [$c('Blue', '#4767B0'), $c('Peach', '#F2A07B'), $c('Green', '#3FA27A')], $men, ['new'], 'Cotton', 'Hand-dyed shibori in soft ombré tones. No two pieces are alike.'],
            ['bandhani-silk-dupatta', 'Bandhani Silk Dupatta', 'dupattas', 3800, null, ['bandhani', 'saree_red'], [$c('Red', '#B0182A'), $c('Mustard', '#D8A31A')], $free, ['best'], 'Gaji silk', 'Hand-tied Kutchi bandhani with a gota-patti border.'],
            ['kundan-cocktail-ring', 'Kundan Cocktail Ring', 'jewellery', 2600, null, ['ring', 'necklace'], [$c('Silver', '#C8CBCF')], $one, [], 'Silver plated', 'A statement floral ring set with amethyst and citrine stones. Adjustable band.'],
            ['layered-temple-necklace', 'Layered Temple Necklace', 'jewellery', 6400, 7800, ['necklace', 'pendant'], [$c('Gold', '#C9A961')], $one, ['best'], '22k gold plated', 'A two-layer gold-plated chain with a crescent drop and a stone pendant.'],
            ['heart-drop-earrings', 'Heart Drop Earrings', 'jewellery', 1800, null, ['earrings', 'bangle'], [$c('Sapphire', '#2B59C3')], $one, ['new'], 'Gold plated brass', 'Lightweight hook earrings with faceted sapphire-blue crystals.'],
            ['paisley-kada-bangle', 'Paisley Kada Bangle', 'jewellery', 4200, null, ['bangle', 'ring'], [$c('Rose gold', '#D7A07C')], ['2.4', '2.6', '2.8'], [], 'Rose gold plated', 'An openwork paisley kada studded with cubic zirconia.'],
        ];

        $models = [];
        foreach ($rows as $i => [$slug, $name, $category, $price, $compare, $photos, $colors, $sizes, $flags, $material, $desc]) {
            $models[$slug] = Product::withTrashed()->updateOrCreate(['slug' => $slug], [
                'name' => $name, 'category_id' => $cat[$category]->id, 'sku' => 'EF-'.(1001 + $i),
                'price' => $price, 'compare_at_price' => $compare,
                'images' => array_map(fn ($p) => $img($ph[$p]), $photos),
                'colors' => $colors, 'sizes' => $sizes, 'stock' => mt_rand(6, 60),
                'is_active' => true, 'is_new' => in_array('new', $flags, true), 'is_best_seller' => in_array('best', $flags, true),
                'brand' => 'Ethnic Fashion Store', 'material' => $material, 'materials' => $material,
                'care' => str_contains(strtolower($material), 'silk') || $category === 'jewellery' ? 'Dry clean only. Store wrapped in muslin.' : 'Gentle hand wash in cold water. Dry in shade.',
                'description' => $desc, 'sort_order' => $i, 'template' => self::TEMPLATE, 'client_id' => $this->clientId, 'deleted_at' => null,
            ]);
        }

        // Products sold per variant: each size gets its own SKU, price and stock. Stock is fixed
        // here (not mt_rand) so the seeded random sequence used for orders stays the same.
        // size => [price, stock]
        $variants = [
            'paisley-kada-bangle' => ['2.4' => [4200, 12], '2.6' => [4400, 18], '2.8' => [4600, 10]],
        ];
        foreach ($variants as $slug => $bySize) {
            $product = $models[$slug];
            $product->update([
                'has_variants' => true,
                'variant_attributes' => [['name' => 'Size', 'values' => array_map('strval', array_keys($bySize)), 'swatches' => []]],
                'variants' => array_map(fn ($size, $row) => [
                    'options' => ['Size' => (string) $size],
                    'sku' => $product->sku.'-'.str_replace('.', '', (string) $size),
                    'price' => $row[0], 'stock' => $row[1], 'is_active' => true,
                ], array_keys($bySize), $bySize),
                // The listing price is the lowest variant price ("from ₹…").
                'price' => min(array_column($bySize, 0)),
                'stock' => array_sum(array_column($bySize, 1)),
            ]);
        }

        $this->tshirt($img, $ph, $cat['menswear']);

        $collections = [
            ['wedding-edit', 'The Wedding Edit', 'Wedding Season', 'Bridal silks, sangeet gowns and heirloom jewellery for every function.', 'saree_red', ['kanjivaram-bridal-saree', 'banarasi-silk-saree-purple', 'floral-printed-lehenga-gown', 'layered-temple-necklace', 'checked-bandhgala-tuxedo'], true],
            ['festive-favourites', 'Festive Favourites', 'Diwali', 'Colour, shine and comfort for the festival season.', 'anarkali_white', ['ivory-chikankari-anarkali', 'scarlet-flared-anarkali-gown', 'bandhani-silk-dupatta', 'paisley-kada-bangle', 'cotton-festive-shirt'], false],
            ['everyday-ethnic', 'Everyday Ethnic', null, 'Easy kurta sets and light drapes to wear on repeat.', 'kurta_green', ['emerald-printed-kurta-set', 'maroon-velvet-kurta-dress', 'chanderi-saree-charcoal', 'shibori-dyed-shirt', 'heart-drop-earrings'], false],
            ['modern-fusion', 'Modern Fusion', null, 'Pre-draped sarees, maxis and Indo-Western pieces.', 'maxi_teal', ['red-pre-draped-saree-gown', 'teal-georgette-maxi', 'tissue-silk-saree-mint', 'kundan-cocktail-ring'], false],
            ['his-edit', 'His Edit', null, 'Jodhpuris, bandhgalas and festive shirts.', 'men_navy', ['navy-jodhpuri-suit', 'checked-bandhgala-tuxedo', 'cotton-festive-shirt', 'shibori-dyed-shirt'], false],
        ];
        foreach ($collections as $i => [$slug, $name, $season, $desc, $photo, $slugs, $featured]) {
            $collection = Collection::withTrashed()->updateOrCreate(['slug' => $slug], [
                'name' => $name, 'season' => $season, 'description' => $desc, 'image' => $wide($ph[$photo]),
                'is_active' => true, 'is_featured' => $featured, 'sort_order' => $i,
                'template' => self::TEMPLATE, 'client_id' => $this->clientId, 'deleted_at' => null,
            ]);
            $collection->products()->sync(collect($slugs)->mapWithKeys(fn ($s, $k) => [$models[$s]->id => ['sort_order' => $k]])->all());
        }

        return $models;
    }

    /**
     * A T-shirt sold by Colour × Size, each combination with its own SKU, price and stock.
     * Kept out of the catalogue rows (and the returned models) so adding it leaves the seeded
     * random sequence, and so the demo orders and reviews, exactly as they were.
     */
    private function tshirt(\Closure $img, array $ph, Category $category): Product
    {
        $colours = [['name' => 'White', 'hex' => '#F7F5F0'], ['name' => 'Black', 'hex' => '#1C1C1E']];
        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
        $price = fn (string $size) => $size === 'XXL' ? 1399 : 1299;
        // colour => size => stock
        $stock = [
            'White' => ['S' => 14, 'M' => 22, 'L' => 20, 'XL' => 12, 'XXL' => 6],
            'Black' => ['S' => 10, 'M' => 25, 'L' => 18, 'XL' => 9, 'XXL' => 0],
        ];

        $sku = 'EF-TEE';
        $variants = [];
        foreach ($colours as $colour) {
            foreach ($sizes as $size) {
                $variants[] = [
                    'options' => ['Colour' => $colour['name'], 'Size' => $size],
                    'sku' => $sku.'-'.strtoupper(substr($colour['name'], 0, 3)).'-'.$size,
                    'price' => $price($size), 'stock' => $stock[$colour['name']][$size], 'is_active' => true,
                ];
            }
        }

        return Product::withTrashed()->updateOrCreate(['slug' => 'classic-cotton-crew-tshirt'], [
            'name' => 'Classic Cotton Crew T-Shirt', 'category_id' => $category->id, 'sku' => $sku,
            'price' => 1299, 'compare_at_price' => null,
            'images' => [$img($ph['tee_white']), $img($ph['tee_black'])],
            'colors' => $colours, 'sizes' => $sizes,
            'has_variants' => true,
            'variant_attributes' => [
                ['name' => 'Colour', 'values' => array_column($colours, 'name'), 'swatches' => array_column($colours, 'hex', 'name')],
                ['name' => 'Size', 'values' => $sizes, 'swatches' => []],
            ],
            'variants' => $variants,
            'stock' => array_sum(array_column($variants, 'stock')),
            'is_active' => true, 'is_new' => true, 'is_best_seller' => false,
            'brand' => 'Ethnic Fashion Store', 'material' => '100% combed cotton', 'materials' => '100% combed cotton',
            'care' => 'Machine wash cold, inside out. Do not bleach. Dry in shade.',
            'description' => 'A regular-fit crew-neck tee in soft 180 GSM combed cotton. Pre-shrunk, with a ribbed collar that holds its shape.',
            'sort_order' => 100, 'template' => self::TEMPLATE, 'client_id' => $this->clientId, 'deleted_at' => null,
        ]);
    }

    private function coupons(): void
    {
        foreach ([
            ['code' => 'FESTIVE15', 'description' => '15% off festive wear', 'type' => 'percent', 'value' => 15, 'min_subtotal' => 5000],
            ['code' => 'WEDDING3000', 'description' => '₹3,000 off orders over ₹25,000', 'type' => 'fixed', 'value' => 3000, 'min_subtotal' => 25000],
            ['code' => 'FIRSTDRAPE', 'description' => '10% off your first order', 'type' => 'percent', 'value' => 10, 'min_subtotal' => 0],
            ['code' => 'FREESHIP', 'description' => 'Complimentary shipping', 'type' => 'free_shipping', 'value' => 0, 'min_subtotal' => 0],
        ] as $c) {
            Coupon::withTrashed()->updateOrCreate(['code' => $c['code']], $c + [
                'is_active' => true, 'template' => self::TEMPLATE, 'client_id' => $this->clientId, 'deleted_at' => null,
            ]);
        }
    }

    /** @return array<int,array{customer:Customer,address:array}> */
    private function customers(): array
    {
        $people = [
            ['Ananya Iyer', 'Chennai', 'Tamil Nadu', '600028', '14 Boat Club Road, R A Puram'],
            ['Riya Mehta', 'Mumbai', 'Maharashtra', '400050', '7 Carter Road, Bandra West'],
            ['Kavya Reddy', 'Hyderabad', 'Telangana', '500034', 'Plot 22, Road No. 10, Banjara Hills'],
            ['Ishita Sharma', 'New Delhi', 'Delhi', '110049', 'B-41 Hauz Khas Enclave'],
            ['Meghna Nair', 'Kochi', 'Kerala', '682020', '3rd Cross, Panampilly Nagar'],
            ['Pooja Agarwal', 'Jaipur', 'Rajasthan', '302001', '12 Civil Lines'],
            ['Sneha Kulkarni', 'Pune', 'Maharashtra', '411004', '88 Prabhat Road, Erandwane'],
            ['Arjun Malhotra', 'Gurugram', 'Haryana', '122002', 'Tower 5, DLF Phase 4'],
            ['Rahul Verma', 'Lucknow', 'Uttar Pradesh', '226010', '19 Gomti Nagar Extension'],
            ['Divya Menon', 'Bengaluru', 'Karnataka', '560038', '201 CMH Road, Indiranagar'],
            ['Nisha Patel', 'Ahmedabad', 'Gujarat', '380015', '5 Satellite Road'],
            ['Tanvi Joshi', 'Indore', 'Madhya Pradesh', '452001', '44 Palasia Square'],
            ['Vikram Singh', 'Chandigarh', 'Chandigarh', '160017', 'House 1120, Sector 18'],
            ['Aishwarya Rao', 'Mysuru', 'Karnataka', '570002', '9 Kalidasa Road, V V Mohalla'],
            ['Sanya Kapoor', 'Kolkata', 'West Bengal', '700019', '23 Ballygunge Park'],
            ['Farah Khan', 'Bhopal', 'Madhya Pradesh', '462003', '6 Arera Colony'],
        ];

        $password = Hash::make('password');
        $out = [];
        foreach ($people as $i => [$name, $city, $state, $pin, $line1]) {
            $email = strtolower(str_replace(' ', '.', $name)).'@example.com';
            $phone = '+91 9'.str_pad((string) (812340000 + $i * 7919), 9, '0', STR_PAD_LEFT);
            $joined = Carbon::now()->subDays(mt_rand(95, 240));

            $customer = Customer::withTrashed()->updateOrCreate(
                ['client_id' => $this->clientId, 'email' => $email],
                ['name' => $name, 'phone' => $phone, 'password' => $password, 'is_active' => $i !== 15],
            );
            if ($customer->trashed()) {
                $customer->restore();
            }
            if ($customer->wasRecentlyCreated) {
                $customer->forceFill(['created_at' => $joined, 'updated_at' => $joined])->saveQuietly();
            }

            $address = ['name' => $name, 'phone' => $phone, 'line1' => $line1, 'line2' => null, 'city' => $city, 'state' => $state, 'postal_code' => $pin, 'country' => 'India'];
            Address::updateOrCreate(['customer_id' => $customer->id, 'label' => 'Home'], $address + ['is_default' => true]);

            $out[] = ['customer' => $customer, 'address' => $address];
        }

        return $out;
    }

    private function reviews(array $products, array $customers): void
    {
        $reviews = [
            ['kanjivaram-bridal-saree', 5, 'Wore it for my wedding', 'The zari is rich and the silk has a beautiful weight. My mother approved, which says everything.'],
            ['kanjivaram-bridal-saree', 5, 'Heirloom quality', 'Colours are exactly as pictured. The border is stunning in person.'],
            ['banarasi-silk-saree-purple', 5, 'Gorgeous drape', 'Soft enough to pleat easily, and the pallu is a showstopper.'],
            ['banarasi-silk-saree-purple', 4, 'Lovely, slightly heavy', 'Beautiful saree. It is heavier than I expected, but that is the real silk.'],
            ['ivory-chikankari-anarkali', 5, 'Elegant and comfortable', 'The chikankari is delicate and the fit is perfect. Got so many compliments at the sangeet.'],
            ['emerald-printed-kurta-set', 5, 'My office favourite', 'Breathable cotton, the colour has not faded after many washes.'],
            ['emerald-printed-kurta-set', 4, 'Runs a bit large', 'Lovely print. Size down if you are between sizes.'],
            ['checked-bandhgala-tuxedo', 5, 'Sharp fit', 'Tailoring is excellent. Wore it to a reception and felt great.'],
            ['cotton-festive-shirt', 4, 'Good everyday shirt', 'Crisp fabric, true to size. Wish there were more colours.'],
            ['bandhani-silk-dupatta', 5, 'Authentic bandhani', 'You can tell it is hand-tied. Pairs well with a plain kurta.'],
            ['layered-temple-necklace', 5, 'Looks like real gold', 'Did not tarnish after a full wedding weekend. Great value.'],
            ['floral-printed-lehenga-gown', 4, 'Beautiful print', 'The skirt has a lovely flare. The halter neck needed a small alteration.'],
            ['teal-georgette-maxi', 5, 'So flowy', 'Perfect for a day function. Light and twirl-worthy.'],
            ['heart-drop-earrings', 3, 'Pretty but small', 'Nice colour, but smaller than they look in photos.'],
        ];

        $moderation = app(ReviewModeration::class);
        foreach ($reviews as $i => [$slug, $rating, $title, $body]) {
            // Prefer a customer who received this product (a verified purchase); else anyone.
            $buyers = array_values(array_filter($customers, fn ($c) => $moderation->hasReceived($c['customer'], $products[$slug])));
            $who = ($buyers[$i % max(1, count($buyers))] ?? $customers[$i % count($customers)])['customer'];
            $verified = $moderation->hasReceived($who, $products[$slug]);
            Review::withTrashed()->updateOrCreate(
                ['product_id' => $products[$slug]->id, 'title' => $title],
                ['customer_id' => $who->id, 'name' => $who->name, 'email' => $who->email, 'rating' => $rating, 'body' => $body,
                    'is_verified_purchase' => $verified, 'is_approved' => $i !== 13,
                    'held_reason' => $i === 13 ? ($verified ? null : ReviewModeration::HOLD_NOT_VERIFIED) : null, 'template' => self::TEMPLATE, 'client_id' => $this->clientId, 'deleted_at' => null,
                    'created_at' => Carbon::now()->subDays(mt_rand(2, 60))],
            );
        }

        foreach ($products as $product) {
            $approved = Review::query()->where('product_id', $product->id)->where('is_approved', true);
            $count = (clone $approved)->count();
            $product->forceFill(['review_count' => $count, 'rating' => $count ? round((clone $approved)->avg('rating'), 1) : 0])->saveQuietly();
        }
    }

    private function orders(array $products, array $customers): void
    {
        // Replace earlier demo orders so a re-run does not pile them up.
        $old = Order::withTrashed()->where('number', 'like', 'EF-%')->pluck('id');
        OrderItem::withTrashed()->whereIn('order_id', $old)->forceDelete();
        Order::withTrashed()->whereIn('id', $old)->forceDelete();

        $products = array_values($products);
        $methods = [['standard', 250, 15000], ['standard', 250, 15000], ['standard', 250, 15000], ['express', 650, 40000], ['pickup', 0, null]];
        $payments = ['upi', 'upi', 'card', 'card', 'cod', 'cod', 'netbanking', 'wallet'];
        $carriers = ['Delhivery', 'Blue Dart', 'DTDC', 'Ecom Express'];
        $coupons = ['FESTIVE15' => ['percent', 15, 5000], 'WEDDING3000' => ['fixed', 3000, 25000], 'FIRSTDRAPE' => ['percent', 10, 0], 'FREESHIP' => ['free_shipping', 0, 0]];
        $pipeline = array_keys(Order::STATUSES);

        for ($n = 0; $n < 45; $n++) {
            // The first 33 orders span 90–10 days ago and have finished; the last 12 fall in the
            // past 9 days and are spread along the pipeline (newest = just confirmed).
            $recent = $n >= 33;
            $daysAgo = $recent ? intdiv(44 - $n, 4) * 3 + mt_rand(0, 2) : 90 - (int) round($n * 80 / 33);
            $placed = Carbon::now()->subDays($daysAgo)->setTime(mt_rand(9, 22), mt_rand(0, 59), mt_rand(0, 59));
            if ($placed->isFuture()) {
                $placed = Carbon::now()->subHours(mt_rand(1, 6));
            }
            $guest = mt_rand(1, 100) <= 15;
            $who = $customers[mt_rand(0, count($customers) - 1)];

            if (mt_rand(1, 100) <= 8) {
                $status = Order::STATUS_CANCELLED;
            } elseif (! $recent) {
                $status = Order::STATUS_DELIVERED;
            } else {
                // 44..33 → confirmed, confirmed, processing, processing, shipped, shipped, out_for_delivery ×3, delivered ×3
                $status = $pipeline[[0, 0, 1, 1, 2, 2, 3, 3, 3, 4, 4, 4][44 - $n]];
            }

            // Basket: 1–3 distinct products.
            $lines = [];
            $picked = (array) array_rand($products, mt_rand(1, 3));
            foreach ($picked as $idx) {
                $p = $products[$idx];
                $qty = $p->price < 5000 && mt_rand(0, 2) === 0 ? 2 : 1;
                $size = $p->sizes[array_rand($p->sizes)] ?? null;
                $color = $p->colors ? $p->colors[array_rand($p->colors)]['name'] : null;
                $lines[] = ['product' => $p, 'qty' => $qty, 'size' => $size, 'color' => $color];
            }
            $subtotal = array_sum(array_map(fn ($l) => $l['product']->price * $l['qty'], $lines));

            [$shipMethod, $shipCost, $freeOver] = $methods[array_rand($methods)];
            $shipping = $freeOver !== null && $subtotal >= $freeOver ? 0 : $shipCost;

            $code = null;
            $discount = 0;
            if (mt_rand(1, 100) <= 30) {
                $code = array_rand($coupons);
                [$type, $value, $min] = $coupons[$code];
                if ($subtotal < $min) {
                    $code = null;
                } elseif ($type === 'percent') {
                    $discount = (int) round($subtotal * $value / 100);
                } elseif ($type === 'fixed') {
                    $discount = $value;
                } else {
                    $shipping = 0;
                }
            }

            $payment = $payments[array_rand($payments)];
            $paymentStatus = match (true) {
                $status === Order::STATUS_CANCELLED => $payment === 'cod' ? 'pending' : 'refunded',
                $payment === 'cod' => $status === 'delivered' ? 'paid' : 'cod',
                default => 'paid',
            };
            $codFee = $payment === 'cod' ? 99 : 0;

            // Timeline: one step every 1–2 days from the order date, never in the future.
            $history = [];
            $at = $placed->copy();
            $stamps = [];
            $steps = $status === Order::STATUS_CANCELLED
                ? array_merge(array_slice($pipeline, 0, mt_rand(1, 2)), [Order::STATUS_CANCELLED])
                : array_slice($pipeline, 0, array_search($status, $pipeline, true) + 1);
            foreach ($steps as $k => $step) {
                if ($k > 0) {
                    $at = $at->copy()->addHours(mt_rand(10, 40));
                    if ($at->isFuture()) {
                        $at = Carbon::now()->subMinutes(mt_rand(5, 120));
                    }
                }
                $history[] = array_filter(['status' => $step, 'at' => $at->toIso8601String(), 'note' => $step === Order::STATUS_CANCELLED ? 'Customer requested cancellation' : null]);
                $stamps[$step] = $at->copy();
            }

            $address = $guest ? [
                'name' => 'Guest '.['Shopper', 'Buyer', 'Customer'][mt_rand(0, 2)], 'phone' => '+91 98'.mt_rand(10000000, 99999999),
                'line1' => mt_rand(2, 180).' MG Road', 'line2' => null, 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '5600'.mt_rand(10, 99), 'country' => 'India',
            ] : $who['address'];
            $dispatched = $stamps['shipped'] ?? null;

            $order = Order::query()->create([
                'number' => 'EF-'.$placed->format('ymd').'-'.strtoupper(substr(md5((string) $n), 0, 4)),
                'client_id' => $this->clientId, 'template' => self::TEMPLATE,
                'customer_id' => $guest ? null : $who['customer']->id,
                'email' => $guest ? 'guest'.$n.'@example.com' : $who['customer']->email,
                'phone' => $address['phone'],
                'shipping_name' => $address['name'], 'shipping_line1' => $address['line1'], 'shipping_line2' => $address['line2'],
                'shipping_city' => $address['city'], 'shipping_state' => $address['state'],
                'shipping_postal_code' => $address['postal_code'], 'shipping_country' => $address['country'],
                'shipping_method' => $shipMethod, 'shipping_cost' => $shipping + $codFee,
                'payment_method' => $payment, 'payment_status' => $paymentStatus,
                'subtotal' => $subtotal, 'discount' => $discount, 'coupon_code' => $code, 'tax' => 0,
                'total' => max(0, $subtotal - $discount) + $shipping + $codFee,
                'status' => $status, 'status_history' => $history,
                'carrier' => $dispatched && $shipMethod !== 'pickup' ? $carriers[array_rand($carriers)] : null,
                'tracking_number' => $dispatched && $shipMethod !== 'pickup' ? strtoupper(substr(md5('t'.$n), 0, 12)) : null,
                'estimated_delivery' => $placed->copy()->addDays($shipMethod === 'express' ? 2 : 5)->toDateString(),
                'dispatched_at' => $dispatched, 'delivered_at' => $stamps['delivered'] ?? null,
                'cancelled_at' => $stamps['cancelled'] ?? null,
                'cancellation_reason' => isset($stamps['cancelled']) ? 'Customer requested cancellation' : null,
                'notes' => mt_rand(1, 100) <= 12 ? 'Please gift wrap.' : null,
                'created_at' => $placed, 'updated_at' => end($stamps) ?: $placed,
            ]);

            foreach ($lines as $l) {
                $p = $l['product'];
                OrderItem::query()->create([
                    'order_id' => $order->id, 'product_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku,
                    'image' => $p->images[0] ?? null, 'size' => $l['size'], 'color' => $l['color'],
                    'options' => array_filter(['Colour' => $l['color'], 'Size' => $l['size']]),
                    'price' => $p->price, 'qty' => $l['qty'], 'total' => $p->price * $l['qty'],
                    'created_at' => $placed, 'updated_at' => $placed,
                ]);
            }
        }
    }
}
