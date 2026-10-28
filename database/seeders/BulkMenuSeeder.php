<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Navigation menus for the Bulk Order template (its own locations, so the other
 * templates' menus are untouched). Re-running resets only these menus' items.
 */
class BulkMenuSeeder extends Seeder
{
    public function run(): void
    {
        // Category bar under the search: categories come from the catalogue; these are the extra links.
        $this->menu('bulk_header', 'Bulk Order — Header navigation', [
            ['label' => 'Shop all', 'url' => '/shop'],
            ['label' => 'Request a quote', 'url' => '/contact?subject=Quote'],
        ]);

        $this->menu('bulk_footer_shop', 'Bulk Order — Footer shop', [
            ['label' => 'Bulk order', 'url' => '/bulk-order'],
            ['label' => 'Pantry staples', 'url' => '/shop/pantry-staples'],
            ['label' => 'Tea, coffee & beverages', 'url' => '/shop/beverages'],
            ['label' => 'Snacks & biscuits', 'url' => '/shop/snacks-biscuits'],
            ['label' => 'Cleaning & hygiene', 'url' => '/shop/cleaning-hygiene'],
            ['label' => 'All products', 'url' => '/shop'],
        ]);

        $this->menu('bulk_footer_support', 'Bulk Order — Footer customer support', [
            ['label' => 'Contact us', 'url' => '/contact'],
            ['label' => 'Track your order', 'url' => '/track-order'],
            ['label' => 'Shipping', 'url' => '/shipping'],
            ['label' => 'Returns', 'url' => '/returns'],
            ['label' => 'FAQs', 'url' => '/faq'],
        ]);

        $this->menu('bulk_footer_company', 'Bulk Order — Footer company', [
            ['label' => 'About us', 'url' => '/about'],
            ['label' => 'Contact', 'url' => '/contact'],
            ['label' => 'Request a quote', 'url' => '/contact?subject=Quote'],
        ]);

        $this->menu('bulk_legal', 'Bulk Order — Footer policies', [
            ['label' => 'Privacy policy', 'url' => '/privacy'],
            ['label' => 'Terms & conditions', 'url' => '/terms'],
            ['label' => 'Refund policy', 'url' => '/refund-policy'],
        ]);

        Menu::flush();
    }

    private function menu(string $location, string $name, array $items): void
    {
        $menu = Menu::updateOrCreate(['location' => $location], ['name' => $name, 'template' => 'bulk']);
        $menu->allItems()->delete();
        foreach ($items as $i => $item) {
            $menu->allItems()->create($item + ['sort_order' => $i]);
        }
    }
}
