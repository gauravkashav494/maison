<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Support\Media;
use Illuminate\Database\Seeder;

/**
 * Starter catalogue for the Bulk Order template: wholesale pack sizes of pantry staples,
 * beverages, snacks and cleaning supplies. The store's owner replaces it with their own
 * products. Every record is tagged `template = bulk`, so it never appears in the other
 * storefronts. Idempotent.
 */
class BulkCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $img = fn (string $id, int $w = 900, int $h = 900) => Media::unsplash($id, $w, "&h={$h}");

        $categories = [
            ['slug' => 'pantry-staples', 'name' => 'Pantry Staples', 'tagline' => 'Rice, atta, dal, oil & salt in bulk bags', 'image' => $img('1586201375761-83865001e31c')],
            ['slug' => 'beverages', 'name' => 'Tea, Coffee & Beverages', 'tagline' => 'Tea, coffee, soft drinks & juices by the case', 'image' => $img('1544787219-7f47ccb76574')],
            ['slug' => 'snacks-biscuits', 'name' => 'Snacks & Biscuits', 'tagline' => 'Cartons of biscuits, chips & noodles', 'image' => $img('1558961363-fa8fdf82db35')],
            ['slug' => 'cleaning-hygiene', 'name' => 'Cleaning & Hygiene', 'tagline' => 'Floor cleaners, dishwash, soaps & sanitiser', 'image' => $img('1563453392212-326f5e854473')],
        ];
        $cat = [];
        foreach ($categories as $i => $c) {
            $cat[$c['slug']] = Category::updateOrCreate(['slug' => $c['slug']], $c + [
                'sort_order' => $i,
                'description' => $c['tagline'],
                'template' => 'bulk',
                'is_active' => true,
                'show_in_menu' => true,
            ]);
        }

        $products = [
            // ---- Pantry staples ------------------------------------------------------------
            ['slug' => 'bulk-sona-masoori-rice', 'name' => 'Sona Masoori Rice', 'category' => 'pantry-staples', 'price' => 1450, 'compare_at_price' => 1599, 'sizes' => ['25 kg bag', '50 kg bag'], 'images' => [$img('1586201375761-83865001e31c')], 'is_best_seller' => true,
                'description' => 'Aged, medium-grain rice for daily meals in canteens, hostels and restaurants.'],
            ['slug' => 'bulk-whole-wheat-atta', 'name' => 'Whole Wheat Atta', 'category' => 'pantry-staples', 'price' => 1180, 'sizes' => ['25 kg bag', '50 kg bag'], 'images' => [$img('1574323347407-f5e1ad6d020b')], 'is_best_seller' => true,
                'description' => 'Stone-ground whole wheat flour for soft rotis, packed in food-grade bags.'],
            ['slug' => 'bulk-toor-dal', 'name' => 'Toor Dal (Arhar)', 'category' => 'pantry-staples', 'price' => 3400, 'sizes' => ['25 kg bag'], 'images' => [$img('1515543904379-3d757afe72e4')],
                'description' => 'Cleaned, unpolished toor dal for everyday dal and sambar.'],
            ['slug' => 'bulk-sunflower-oil', 'name' => 'Refined Sunflower Oil', 'category' => 'pantry-staples', 'price' => 2150, 'compare_at_price' => 2290, 'sizes' => ['15 L tin', '15 L jar'], 'images' => [$img('1474979266404-7eaacbcd87c5')], 'is_best_seller' => true,
                'description' => 'Light, high smoke-point cooking oil for commercial kitchens.'],
            ['slug' => 'bulk-iodised-salt', 'name' => 'Iodised Salt', 'category' => 'pantry-staples', 'price' => 520, 'sizes' => ['Case of 25 × 1 kg'], 'images' => [$img('1518110925495-5fe2fda0442c')],
                'description' => 'Free-flowing iodised salt, packed in 1 kg pouches.'],

            // ---- Beverages -----------------------------------------------------------------
            ['slug' => 'bulk-assam-tea', 'name' => 'Assam CTC Tea', 'category' => 'beverages', 'price' => 2650, 'sizes' => ['5 kg pack', '10 kg pack'], 'images' => [$img('1544787219-7f47ccb76574')], 'is_best_seller' => true,
                'description' => 'Strong, malty CTC leaf for offices, canteens and tea stalls.'],
            ['slug' => 'bulk-instant-coffee', 'name' => 'Instant Coffee', 'category' => 'beverages', 'price' => 1890, 'sizes' => ['1 kg pack', 'Case of 6 × 500 g'], 'images' => [$img('1497515114629-f71d768fd07c')],
                'description' => 'Pure instant coffee for vending machines and pantries.'],
            ['slug' => 'bulk-cola-cans', 'name' => 'Cola Soft Drink Cans', 'category' => 'beverages', 'price' => 840, 'sizes' => ['Case of 24 × 300 ml'], 'images' => [$img('1554866585-cd94860890b7')], 'max_qty' => 20,
                'description' => 'Chilled-ready cola cans for cafés, events and office fridges.'],
            ['slug' => 'bulk-orange-juice', 'name' => 'Orange Juice', 'category' => 'beverages', 'price' => 1320, 'sizes' => ['Case of 12 × 1 L'], 'images' => [$img('1600271886742-f049cd451bba')],
                'description' => 'Ready-to-serve orange juice in 1 litre packs.'],

            // ---- Snacks & biscuits ----------------------------------------------------------
            ['slug' => 'bulk-glucose-biscuits', 'name' => 'Glucose Biscuits', 'category' => 'snacks-biscuits', 'price' => 960, 'sizes' => ['Carton of 48 packs', 'Carton of 96 packs'], 'images' => [$img('1558961363-fa8fdf82db35')], 'is_best_seller' => true,
                'description' => 'Classic glucose biscuits in single-serve packs.'],
            ['slug' => 'bulk-salted-chips', 'name' => 'Salted Potato Chips', 'category' => 'snacks-biscuits', 'price' => 1150, 'sizes' => ['Carton of 60 × 26 g'], 'images' => [$img('1566478989037-eec170784d0b')],
                'description' => 'Crisp salted chips in counter-friendly small packs.'],
            ['slug' => 'bulk-instant-noodles', 'name' => 'Instant Masala Noodles', 'category' => 'snacks-biscuits', 'price' => 1080, 'compare_at_price' => 1150, 'sizes' => ['Carton of 96 × 70 g'], 'images' => [$img('1612927601601-6638404737ce')],
                'description' => 'Two-minute masala noodles by the carton.'],

            // ---- Cleaning & hygiene ---------------------------------------------------------
            ['slug' => 'bulk-floor-cleaner', 'name' => 'Floor Cleaner — Citrus', 'category' => 'cleaning-hygiene', 'price' => 690, 'sizes' => ['5 L can', 'Case of 4 × 5 L'], 'images' => [$img('1563453392212-326f5e854473')], 'is_best_seller' => true,
                'description' => 'Disinfectant floor cleaner for offices, shops and hospitality.'],
            ['slug' => 'bulk-liquid-hand-wash', 'name' => 'Liquid Hand Wash', 'category' => 'cleaning-hygiene', 'price' => 540, 'sizes' => ['5 L can'], 'images' => [$img('1571781926291-c477ebfd024b')],
                'description' => 'Gentle, pH-balanced hand wash refill for dispensers.'],
        ];

        foreach ($products as $i => $data) {
            $categorySlug = $data['category'];
            unset($data['category']);
            Product::updateOrCreate(['slug' => $data['slug']], $data + [
                'category_id' => $cat[$categorySlug]->id,
                'sort_order' => $i,
                'sku' => 'SU-'.str_pad((string) ($i + 1001), 4, '0', STR_PAD_LEFT),
                'colors' => [],
                'stock' => 200,
                'is_active' => true,
                'template' => 'bulk',
            ]);
        }
    }
}
