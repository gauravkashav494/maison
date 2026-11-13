<?php

namespace Database\Seeders;

use App\Clients\ClientContext;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Starter catalogue for the Bulk Order template: trade supplies for kitchens, cleaning teams
 * and offices, each with a product code. Some products are sold by variant (gloves by size,
 * containers by capacity), each variant with a code of its own, so the order form lists them
 * as separate rows and the quick-order pad can find them. Every row is tagged
 * `template = bulk`, so it never appears in another storefront. Idempotent.
 */
class BulkCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // In a shared database the lookups must stay inside this client, or a re-run would
        // reach another client's rows; in a client database it is simply the only client.
        $client = ['client_id' => app(ClientContext::class)->id()];

        $categories = [
            ['slug' => 'cleaning', 'name' => 'Cleaning Chemicals', 'tagline' => 'Floor, surface, kitchen and washroom'],
            ['slug' => 'disposables', 'name' => 'Packaging & Disposables', 'tagline' => 'Containers, cups, bags and foil'],
            ['slug' => 'hygiene', 'name' => 'Gloves & Hygiene', 'tagline' => 'Gloves, hairnets, aprons and sanitiser'],
            ['slug' => 'kitchen', 'name' => 'Kitchen Consumables', 'tagline' => 'Cling film, baking paper and tissue'],
            ['slug' => 'office', 'name' => 'Office Supplies', 'tagline' => 'Paper, files and everyday stationery'],
        ];

        $cat = [];
        foreach ($categories as $i => $c) {
            $cat[$c['slug']] = Category::updateOrCreate(['slug' => $c['slug'], 'template' => 'bulk'] + $client, $c + [
                'sort_order' => $i,
                'description' => $c['tagline'],
                'is_active' => true,
                'show_in_menu' => true,
            ]);
        }

        // name, code, price (₹), stock, pack
        $products = [
            'cleaning' => [
                ['Floor cleaner concentrate, pine, 5 L', 'CL-FC-5L', 540, 220, 'can'],
                ['Hard-surface sanitiser, 5 L', 'CL-HS-5L', 690, 140, 'can'],
                ['Dishwash liquid, lime, 5 L', 'CL-DW-5L', 410, 310, 'can'],
                ['Toilet bowl cleaner, 1 L', 'CL-TB-1L', 115, 480, 'bottle'],
                ['Glass cleaner, trigger spray, 500 ml', 'CL-GC-500', 95, 0, 'bottle'],
            ],
            'disposables' => [
                ['Paper cup, 200 ml, single wall', 'PK-PC-200', 145, 600, '100 pcs/sleeve'],
                ['Aluminium foil roll, 72 m', 'PK-AF-72', 620, 85, 'roll'],
                ['Garbage bag, 30 × 37 in, black', 'PK-GB-L', 260, 340, '50 pcs/pack'],
                ['Kraft carry bag with handle', 'PK-KB-M', 390, 150, '50 pcs/pack'],
            ],
            'hygiene' => [
                ['Disposable hairnet, white', 'GL-HN-W', 180, 420, '100 pcs/pack'],
                ['Polythene apron, disposable', 'GL-AP-PE', 210, 260, '100 pcs/pack'],
                ['Hand sanitiser, 5 L', 'GL-HS-5L', 980, 75, 'can'],
            ],
            'kitchen' => [
                ['Cling film, 300 mm × 300 m', 'KT-CF-300', 760, 90, 'roll'],
                ['Baking paper, 45 × 75 cm', 'KT-BP-4575', 1150, 40, '500 sheets'],
                ['Tissue napkin, 2 ply, 30 × 30 cm', 'KT-TN-30', 85, 900, '100 pcs/pack'],
                ['Kitchen towel roll, 2 ply', 'KT-KR-2P', 240, 380, '6 rolls/pack'],
            ],
            'office' => [
                ['A4 copier paper, 75 gsm', 'OF-A4-75', 290, 520, 'ream'],
                ['Lever arch file, foolscap', 'OF-LA-FS', 135, 260, 'piece'],
                ['Ballpoint pen, blue', 'OF-BP-BL', 85, 410, '10 pcs/box'],
                ['Permanent marker, black', 'OF-MK-BK', 320, 120, '10 pcs/box'],
            ],
        ];

        // A few lines on trade offer, so the order form's discount column has something to show.
        $was = ['CL-FC-5L' => 600, 'CL-DW-5L' => 450, 'KT-TN-30' => 95, 'OF-A4-75' => 320];
        // Flags for the product page's "Most ordered" badge and the catalogue's new items.
        $bestsellers = ['CL-FC-5L', 'CL-DW-5L', 'PK-PC-200', 'PK-GB-L', 'KT-KR-2P', 'OF-A4-75', 'GL-HN-W', 'KT-CF-300'];
        $new = ['CL-HS-5L', 'PK-KB-M', 'GL-HS-5L', 'KT-BP-4575'];

        $sort = 0;
        foreach ($products as $slug => $rows) {
            foreach ($rows as [$name, $sku, $price, $stock, $pack]) {
                $this->product($client, $cat[$slug], $sort++, $name, $sku, [
                    'price' => $price,
                    'compare_at_price' => $was[$sku] ?? null,
                    'is_best_seller' => in_array($sku, $bestsellers, true),
                    'is_new' => in_array($sku, $new, true),
                    'stock' => $stock,
                    'sizes' => [$pack],
                ]);
            }
        }

        // Sold by variant: each size or capacity has its own code, price and stock.
        $this->variants($client, $cat['hygiene'], $sort++, 'Nitrile gloves, powder free, blue', 'GL-NT', '100 pcs/box', [
            'S' => ['GL-NT-S', 420, 180],
            'M' => ['GL-NT-M', 420, 340],
            'L' => ['GL-NT-L', 420, 290],
            'XL' => ['GL-NT-XL', 460, 0],
        ]);
        $this->variants($client, $cat['disposables'], $sort++, 'Round food container with lid, clear', 'PK-CT', '50 pcs/sleeve', [
            '250 ml' => ['PK-CT-250', 210, 260],
            '500 ml' => ['PK-CT-500', 265, 410],
            '750 ml' => ['PK-CT-750', 310, 220],
            '1000 ml' => ['PK-CT-1000', 355, 140],
        ]);
    }

    private function product(array $client, Category $category, int $sort, string $name, string $sku, array $values): Product
    {
        return Product::updateOrCreate(
            ['sku' => $sku, 'template' => 'bulk'] + $client,
            $values + [
                'category_id' => $category->id,
                'name' => $name,
                'slug' => str($sku)->lower()->slug()->value(),
                'description' => $name.(isset($values['sizes'][0]) ? ' — supplied per '.$values['sizes'][0].'.' : '.'),
                'images' => [],
                'is_active' => true,
                'sort_order' => $sort,
            ],
        );
    }

    /** @param  array<string, array{0:string, 1:int, 2:int}>  $sizes  size => [code, price, stock] */
    private function variants(array $client, Category $category, int $sort, string $name, string $sku, string $pack, array $sizes): void
    {
        $this->product($client, $category, $sort, $name, $sku, [
            'description' => $name.' — supplied per '.$pack.'.',
            'sizes' => array_map('strval', array_keys($sizes)),
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => array_map('strval', array_keys($sizes)), 'swatches' => []]],
            'variants' => array_map(fn ($size, $row) => [
                'options' => ['Size' => (string) $size],
                'sku' => $row[0], 'price' => $row[1], 'stock' => $row[2], 'is_active' => true,
            ], array_keys($sizes), $sizes),
            'is_best_seller' => true,
            // The listing price is the lowest variant price.
            'price' => min(array_column($sizes, 1)),
            'stock' => array_sum(array_column($sizes, 2)),
        ]);
    }
}
