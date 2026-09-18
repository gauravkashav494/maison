<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Support\Media;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $img = fn (string $id, int $w = 1200, int $h = 1500) => Media::unsplash($id, $w, "&h={$h}");

        $categories = [
            ['slug' => 'clothing', 'name' => 'Clothing', 'tagline' => 'Tailoring, knitwear and outerwear', 'image' => $img('1539533018447-63fcce2678e3')],
            ['slug' => 'perfumes', 'name' => 'Perfumes', 'tagline' => 'Signature scents, bottled', 'image' => $img('1622618991746-fe6004db3a47')],
            ['slug' => 'watches', 'name' => 'Watches', 'tagline' => 'Precision, worn quietly', 'image' => $img('1619134778706-7015533a6150')],
            ['slug' => 'bags', 'name' => 'Bags', 'tagline' => 'Leather, considered', 'image' => $img('1614179689702-355944cd0918')],
            ['slug' => 'belts', 'name' => 'Belts', 'tagline' => 'Bridle leather and brass', 'image' => $img('1624222247344-550fb60583dc')],
            ['slug' => 'accessories', 'name' => 'Accessories', 'tagline' => 'Jewellery, eyewear and small leather goods', 'image' => $img('1602173574767-37ac01994b2a')],
        ];
        $cat = [];
        foreach ($categories as $i => $c) {
            $cat[$c['slug']] = Category::updateOrCreate(['slug' => $c['slug']], $c + ['sort_order' => $i, 'description' => $c['tagline'], 'template' => 'fashion']);
        }

        // Clothing sub-categories (used by the mega menu)
        foreach (['Coats & Jackets' => 'outerwear', 'Tailoring' => 'tailoring', 'Knitwear' => 'knitwear', 'Shirts' => 'shirts', 'Trousers' => 'trousers', 'Footwear' => 'footwear'] as $name => $slug) {
            Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'parent_id' => $cat['clothing']->id, 'sort_order' => 0, 'template' => 'fashion']);
        }

        $p = fn (string $id) => Media::unsplash($id, 900, '&h=1200');
        $black = ['name' => 'Black', 'hex' => '#151412'];

        $products = [
            ['slug' => 'camel-wool-wrap-coat', 'name' => 'Camel Wool Wrap Coat', 'category' => 'clothing', 'price' => 24900, 'images' => [$p('1539533018447-63fcce2678e3'), $p('1551232864-3f0890e580d9')], 'colors' => [['name' => 'Camel', 'hex' => '#B8916A'], ['name' => 'Charcoal', 'hex' => '#2A2724']], 'sizes' => ['XS', 'S', 'M', 'L', 'XL'], 'rating' => 4.9, 'review_count' => 128, 'is_new' => true, 'is_best_seller' => true, 'description' => 'A double-faced wool coat with a fluid wrap silhouette, cut generously through the shoulder and finished with a self-tie belt.', 'materials' => '100% double-faced virgin wool. Lining: cupro.', 'care' => 'Dry clean only.'],
            ['slug' => 'ivory-tailored-blazer', 'name' => 'Ivory Tailored Blazer', 'category' => 'clothing', 'price' => 18500, 'images' => [$p('1571513722275-4b41940f54b8'), $p('1550639525-c97d455acf70')], 'colors' => [['name' => 'Ivory', 'hex' => '#F1ECE2'], $black], 'sizes' => ['XS', 'S', 'M', 'L'], 'rating' => 4.8, 'review_count' => 64, 'is_new' => true, 'description' => 'Single-breasted blazer in a fine Italian wool crêpe with a softly padded shoulder and satin-faced lapel.'],
            ['slug' => 'noir-leather-biker-jacket', 'name' => 'Noir Leather Biker Jacket', 'category' => 'clothing', 'price' => 32000, 'images' => [$p('1551028719-00167b16eac5'), $p('1521223890158-f9f7c3d5d504')], 'colors' => [$black], 'sizes' => ['S', 'M', 'L', 'XL'], 'rating' => 4.9, 'review_count' => 212, 'is_best_seller' => true, 'description' => 'Supple lambskin biker with an asymmetric zip, quilted shoulder panels and gunmetal hardware.'],
            ['slug' => 'essential-cotton-tee', 'name' => 'Essential Cotton Tee', 'category' => 'clothing', 'price' => 3200, 'images' => [$p('1583743814966-8936f5b7be1a'), $p('1521572163474-6864f9cf17ab')], 'colors' => [$black, ['name' => 'White', 'hex' => '#FAFAF8'], ['name' => 'Stone', 'hex' => '#D9D1C5']], 'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'rating' => 4.7, 'review_count' => 540, 'is_best_seller' => true, 'description' => 'Heavyweight organic cotton jersey with a relaxed drop shoulder. Garment-dyed for depth of colour.'],
            ['slug' => 'chambray-relaxed-shirt', 'name' => 'Chambray Relaxed Shirt', 'category' => 'clothing', 'price' => 5900, 'compare_at_price' => 7400, 'images' => [$p('1596755094514-f87e34085b2c'), $p('1602810318383-e386cc2a3ccf')], 'colors' => [['name' => 'Indigo', 'hex' => '#4E5D78']], 'sizes' => ['S', 'M', 'L', 'XL'], 'rating' => 4.6, 'review_count' => 88, 'description' => 'Washed chambray with mother-of-pearl buttons and a curved hem. Wears in beautifully.'],
            ['slug' => 'amber-oud-eau-de-parfum', 'name' => 'Amber Oud Eau de Parfum', 'category' => 'perfumes', 'price' => 9800, 'images' => [$p('1588405748880-12d1d2a59f75'), $p('1622618991746-fe6004db3a47')], 'colors' => [], 'sizes' => ['50ml', '100ml'], 'rating' => 4.9, 'review_count' => 316, 'is_new' => true, 'is_best_seller' => true, 'description' => 'Smoked oud, Siam benzoin and warm amber over a base of sandalwood. Long-wearing and quietly magnetic.'],
            ['slug' => 'rose-vetiver-eau-de-parfum', 'name' => 'Rose Vétiver Eau de Parfum', 'category' => 'perfumes', 'price' => 8400, 'images' => [$p('1613521140785-e85e427f8002'), $p('1615634260167-c8cdede054de')], 'colors' => [], 'sizes' => ['50ml', '100ml'], 'rating' => 4.8, 'review_count' => 142, 'description' => 'Bulgarian rose and pink pepper lifted by Haitian vétiver. A modern floral with an earthy, green finish.'],
            ['slug' => 'heritage-automatic-watch', 'name' => 'Heritage Automatic 40mm', 'category' => 'watches', 'price' => 46000, 'images' => [$p('1619134778706-7015533a6150'), $p('1434056886845-dac89ffe9b56')], 'colors' => [['name' => 'Steel', 'hex' => '#B9BCC0'], ['name' => 'Midnight', 'hex' => '#1F2A44']], 'sizes' => ['One size'], 'rating' => 4.9, 'review_count' => 97, 'is_best_seller' => true, 'description' => 'Swiss automatic movement, sapphire crystal and a brushed steel bracelet. 100m water resistance.'],
            ['slug' => 'minimal-slim-watch', 'name' => 'Minimal Slim Watch 36mm', 'category' => 'watches', 'price' => 14500, 'compare_at_price' => 17900, 'images' => [$p('1524592094714-0f0654e20314'), $p('1434056886845-dac89ffe9b56')], 'colors' => [['name' => 'Tan', 'hex' => '#B8916A'], $black], 'sizes' => ['One size'], 'rating' => 4.7, 'review_count' => 233, 'description' => 'A 6mm-thin case with a matte dial and Italian leather strap. Understated by design.'],
            ['slug' => 'structured-leather-tote', 'name' => 'Structured Leather Tote', 'category' => 'bags', 'price' => 21000, 'images' => [$p('1614179689702-355944cd0918'), $p('1598532163257-ae3c6b2524b6')], 'colors' => [$black, ['name' => 'Cognac', 'hex' => '#9A5B2E']], 'sizes' => ['One size'], 'rating' => 4.8, 'review_count' => 156, 'is_new' => true, 'description' => 'Full-grain calf leather with a suede-lined interior, magnetic closure and detachable pouch.'],
            ['slug' => 'bordeaux-croc-mini-bag', 'name' => 'Bordeaux Croc-Embossed Mini Bag', 'category' => 'bags', 'price' => 16800, 'images' => [$p('1575032617751-6ddec2089882'), $p('1544816155-12df9643f363')], 'colors' => [['name' => 'Bordeaux', 'hex' => '#5C1F2B'], ['name' => 'Tan', 'hex' => '#B8916A']], 'sizes' => ['One size'], 'rating' => 4.8, 'review_count' => 71, 'is_new' => true, 'description' => 'A compact top-handle in croc-embossed calfskin with a polished turn-lock and removable chain strap.'],
            ['slug' => 'signature-leather-belt', 'name' => 'Signature Leather Belt', 'category' => 'belts', 'price' => 6200, 'images' => [$p('1624222247344-550fb60583dc'), $p('1627123424574-724758594e93')], 'colors' => [['name' => 'Cognac', 'hex' => '#9A5B2E'], $black], 'sizes' => ['80', '85', '90', '95', '100'], 'rating' => 4.9, 'review_count' => 402, 'is_best_seller' => true, 'description' => 'Vegetable-tanned bridle leather, 3.5cm wide, with a solid brass buckle that patinas with time.'],
            ['slug' => 'bifold-leather-wallet', 'name' => 'Bifold Leather Wallet', 'category' => 'accessories', 'price' => 5400, 'images' => [$p('1627123424574-724758594e93'), $p('1624222247344-550fb60583dc')], 'colors' => [['name' => 'Chestnut', 'hex' => '#6B3F23'], $black], 'sizes' => ['One size'], 'rating' => 4.8, 'review_count' => 189, 'description' => 'Eight card slots and a full-length note compartment in a slim, hand-burnished leather bifold.'],
            ['slug' => 'gold-chain-bracelet', 'name' => 'Gold Chain Bracelet', 'category' => 'accessories', 'price' => 7900, 'images' => [$p('1602173574767-37ac01994b2a'), $p('1617038220319-276d3cfab638')], 'colors' => [['name' => 'Gold', 'hex' => '#C9A961']], 'sizes' => ['One size'], 'rating' => 4.7, 'review_count' => 54, 'is_new' => true, 'description' => '18k gold-plated curb chain with a lobster clasp. Layers effortlessly, wears alone with confidence.'],
            ['slug' => 'sapphire-drop-earrings', 'name' => 'Sapphire Drop Earrings', 'category' => 'accessories', 'price' => 12400, 'images' => [$p('1535632066927-ab7c9ab60908'), $p('1611652022419-a9419f74343d')], 'colors' => [['name' => 'Silver', 'hex' => '#C8CBCF']], 'sizes' => ['One size'], 'rating' => 4.9, 'review_count' => 38, 'is_new' => true, 'description' => 'Cushion-cut lab sapphires set in a halo of pavé crystals on sterling silver posts.'],
            ['slug' => 'round-metal-sunglasses', 'name' => 'Round Metal Sunglasses', 'category' => 'accessories', 'price' => 8900, 'compare_at_price' => 10900, 'images' => [$p('1511499767150-a48a237f0083'), $p('1566174053879-31528523f8ae')], 'colors' => [['name' => 'Gold', 'hex' => '#C9A961'], ['name' => 'Gunmetal', 'hex' => '#5A5C60']], 'sizes' => ['One size'], 'rating' => 4.6, 'review_count' => 120, 'description' => 'Titanium round frames with green mineral glass lenses and adjustable acetate nose pads.'],
            ['slug' => 'leather-derby-boots', 'name' => 'Leather Derby Boots', 'category' => 'clothing', 'price' => 19800, 'images' => [$p('1608256246200-53e635b5b65f'), $p('1610652492500-ded49ceeb378')], 'colors' => [['name' => 'Espresso', 'hex' => '#3E2A1E'], $black], 'sizes' => ['40', '41', '42', '43', '44', '45'], 'rating' => 4.8, 'review_count' => 76, 'is_new' => true, 'description' => 'Goodyear-welted derby boots in polished calf with a leather sole and rubber heel insert.'],
            ['slug' => 'pearl-strand-necklace', 'name' => 'Pearl Strand Necklace', 'category' => 'accessories', 'price' => 15600, 'images' => [$p('1515562141207-7a88fb7ce338'), $p('1611652022419-a9419f74343d')], 'colors' => [['name' => 'Pearl', 'hex' => '#EFE9E1']], 'sizes' => ['One size'], 'rating' => 4.9, 'review_count' => 45, 'is_best_seller' => true, 'description' => 'Hand-knotted freshwater pearls, 7–8mm, on silk with a sterling silver clasp. 45cm length.'],
        ];

        $models = [];
        foreach ($products as $i => $data) {
            $categorySlug = $data['category'];
            unset($data['category']);
            $models[$data['slug']] = Product::updateOrCreate(
                ['slug' => $data['slug']],
                $data + ['category_id' => $cat[$categorySlug]->id, 'sort_order' => $i, 'sku' => 'ME-'.str_pad((string) ($i + 1001), 4, '0', STR_PAD_LEFT), 'template' => 'fashion'],
            );
        }

        $c = fn (string $id) => Media::unsplash($id, 1400, '&h=1750');
        $collections = [
            ['slug' => 'new-season', 'name' => 'New Season', 'season' => 'Autumn / Winter', 'description' => 'The first chapter of the season — outerwear, tailoring and the pieces that anchor a wardrobe.', 'image' => $c('1526413232644-8a40f03cc03b'), 'products' => ['camel-wool-wrap-coat', 'ivory-tailored-blazer', 'leather-derby-boots', 'structured-leather-tote', 'amber-oud-eau-de-parfum', 'gold-chain-bracelet']],
            ['slug' => 'essentials', 'name' => 'Essentials', 'description' => 'Elevated basics in organic cotton, linen and merino. The foundation of everything else.', 'image' => $c('1558769132-cb1aea458c5e'), 'products' => ['essential-cotton-tee', 'chambray-relaxed-shirt', 'signature-leather-belt', 'bifold-leather-wallet']],
            ['slug' => 'signature', 'name' => 'Signature Collection', 'description' => 'Our most recognisable silhouettes, reimagined each season with new fabrics and finishes.', 'image' => $c('1610652492500-ded49ceeb378'), 'is_featured' => true, 'products' => ['noir-leather-biker-jacket', 'camel-wool-wrap-coat', 'heritage-automatic-watch', 'structured-leather-tote']],
            ['slug' => 'fragrance-edit', 'name' => 'Fragrance Edit', 'description' => 'Six compositions created with master perfumers in Grasse.', 'image' => $c('1622618991746-fe6004db3a47'), 'products' => ['amber-oud-eau-de-parfum', 'rose-vetiver-eau-de-parfum']],
            ['slug' => 'timepieces', 'name' => 'Timepieces', 'description' => 'Swiss-made automatic and quartz watches built to be handed down.', 'image' => $c('1619134778706-7015533a6150'), 'products' => ['heritage-automatic-watch', 'minimal-slim-watch']],
            ['slug' => 'leather', 'name' => 'Leather Collection', 'description' => 'Bags, belts and small leather goods, cut from vegetable-tanned hides.', 'image' => $c('1521223890158-f9f7c3d5d504'), 'products' => ['structured-leather-tote', 'bordeaux-croc-mini-bag', 'signature-leather-belt', 'bifold-leather-wallet', 'noir-leather-biker-jacket']],
            ['slug' => 'weekend-edit', 'name' => 'Weekend Edit', 'description' => 'Softer tailoring, washed fabrics and pieces made for slower days.', 'image' => $c('1596755094514-f87e34085b2c'), 'products' => ['chambray-relaxed-shirt', 'essential-cotton-tee', 'round-metal-sunglasses']],
            ['slug' => 'evening-edit', 'name' => 'Evening Edit', 'description' => 'Sharp lines, deep tones and a little shine for after dark.', 'image' => $c('1550639525-c97d455acf70'), 'products' => ['sapphire-drop-earrings', 'pearl-strand-necklace', 'ivory-tailored-blazer']],
            ['slug' => 'gift-collection', 'name' => 'Gift Collection', 'description' => 'Curated gifts for every occasion, wrapped by hand in our signature ivory box.', 'image' => $c('1617038220319-276d3cfab638'), 'products' => ['gold-chain-bracelet', 'amber-oud-eau-de-parfum', 'bifold-leather-wallet', 'pearl-strand-necklace']],
        ];
        foreach ($collections as $i => $data) {
            $slugs = $data['products'];
            unset($data['products']);
            $collection = Collection::updateOrCreate(['slug' => $data['slug']], $data + ['sort_order' => $i, 'template' => 'fashion']);
            $sync = [];
            foreach ($slugs as $k => $slug) {
                $sync[$models[$slug]->id] = ['sort_order' => $k];
            }
            $collection->products()->sync($sync);
        }
    }
}
