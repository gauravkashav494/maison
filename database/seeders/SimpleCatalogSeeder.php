<?php

namespace Database\Seeders;

use App\Clients\ClientContext;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Starter catalogue for the Simple List template: a plain stock list with codes, prices and
 * quantities and no imagery at all. Every row is tagged `template = simple`, so it never
 * appears in another storefront. Idempotent.
 */
class SimpleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // In a shared database the lookups must stay inside this client, or a re-run would
        // reach another client's rows; in a client database it is simply the only client.
        $client = ['client_id' => app(ClientContext::class)->id()];

        $categories = [
            ['slug' => 'fasteners', 'name' => 'Fasteners', 'tagline' => 'Bolts, nuts, screws and washers'],
            ['slug' => 'adhesives', 'name' => 'Adhesives & Sealants', 'tagline' => 'Solvent cement, tapes and sealants'],
            ['slug' => 'electrical', 'name' => 'Electrical', 'tagline' => 'Cable, switches, fuses and lamps'],
            ['slug' => 'safety', 'name' => 'Safety', 'tagline' => 'Gloves, goggles and signage'],
            ['slug' => 'stationery', 'name' => 'Office & Stationery', 'tagline' => 'Paper, files and everyday supplies'],
        ];

        $cat = [];
        foreach ($categories as $i => $c) {
            $cat[$c['slug']] = Category::updateOrCreate(['slug' => $c['slug'], 'template' => 'simple'] + $client, $c + [
                'sort_order' => $i,
                'description' => $c['tagline'],
                'is_active' => true,
                'show_in_menu' => true,
            ]);
        }

        // name, code, price (₹), stock, unit
        $products = [
            'fasteners' => [
                ['Hex bolt M8 × 40 mm, zinc plated', 'FS-M8-40', 12, 1800, '100 pcs/box'],
                ['Hex bolt M10 × 50 mm, zinc plated', 'FS-M10-50', 18, 1200, '100 pcs/box'],
                ['Hex nut M8, zinc plated', 'FS-N-M8', 4, 5400, '200 pcs/box'],
                ['Flat washer M8, stainless', 'FS-W-M8', 3, 6000, '200 pcs/box'],
                ['Self-tapping screw 8 × 25 mm', 'FS-ST-825', 2, 9500, '500 pcs/box'],
                ['Wall plug 6 mm with screw', 'FS-WP-6', 3, 7400, '100 pcs/pack'],
            ],
            'adhesives' => [
                ['PVC solvent cement, 250 ml', 'AD-PVC-250', 185, 240, 'tin'],
                ['Thread seal tape, 12 mm × 10 m', 'AD-PTFE-12', 22, 860, 'roll'],
                ['Silicone sealant, clear, 280 ml', 'AD-SIL-280', 240, 310, 'cartridge'],
                ['Anaerobic thread locker, 50 ml', 'AD-TL-50', 410, 95, 'bottle'],
                ['Instant adhesive, 20 g', 'AD-CA-20', 95, 420, 'bottle'],
            ],
            'electrical' => [
                ['Copper cable 1.5 sq mm, 90 m', 'EL-CB-15', 1450, 64, 'coil'],
                ['Copper cable 2.5 sq mm, 90 m', 'EL-CB-25', 2320, 48, 'coil'],
                ['Modular switch 6 A, one way', 'EL-SW-6A', 78, 540, 'piece'],
                ['MCB 16 A, single pole', 'EL-MCB-16', 265, 180, 'piece'],
                ['LED lamp 9 W, cool white', 'EL-LED-9', 120, 760, 'piece'],
                ['PVC conduit 20 mm, 3 m', 'EL-CND-20', 95, 430, 'length'],
            ],
            'safety' => [
                ['Nitrile gloves, size L', 'SF-GL-L', 48, 900, 'pair'],
                ['Safety goggles, clear', 'SF-GG-CL', 135, 260, 'piece'],
                ['Dust mask, three ply', 'SF-DM-3P', 6, 4800, '50 pcs/box'],
                ['Warning sign, wet floor', 'SF-SG-WF', 320, 70, 'piece'],
            ],
            'stationery' => [
                ['A4 copier paper, 75 gsm', 'ST-A4-75', 290, 520, 'ream'],
                ['Box file, foolscap', 'ST-BF-FS', 110, 340, 'piece'],
                ['Ballpoint pen, blue', 'ST-BP-BL', 8, 2400, 'piece'],
                ['Stapler pins 24/6', 'ST-SP-246', 14, 1600, 'box'],
                ['Marker pen, permanent black', 'ST-MK-BK', 32, 880, 'piece'],
            ],
        ];

        $sort = 0;
        foreach ($products as $slug => $rows) {
            foreach ($rows as [$name, $sku, $price, $stock, $unit]) {
                Product::updateOrCreate(
                    ['sku' => $sku, 'template' => 'simple'] + $client,
                    [
                        'category_id' => $cat[$slug]->id,
                        'name' => $name,
                        'slug' => str($sku)->lower()->slug()->value(),
                        'description' => $name.' — supplied per '.$unit.'.',
                        'price' => $price,
                        'stock' => $stock,
                        'sizes' => [$unit],
                        'images' => [],            // this template shows no imagery at all
                        'is_active' => true,
                        'sort_order' => $sort++,
                    ],
                );
            }
        }
    }
}
