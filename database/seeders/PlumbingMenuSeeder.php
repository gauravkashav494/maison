<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Navigation menus for the Plumbing template (its own locations, so the other
 * templates' menus are untouched). Re-running resets only these menus' items.
 */
class PlumbingMenuSeeder extends Seeder
{
    public function run(): void
    {
        // Category bar under the search: categories come from the catalogue; these are the extra links.
        $this->menu('plumbing_header', 'Plumbing — Header navigation', [
            ['label' => 'Shop all', 'url' => '/shop'],
            ['label' => 'Brands', 'url' => '/shop?view=brands'],
            ['label' => 'Offers', 'url' => '/shop/sale', 'is_accent' => true],
            ['label' => 'Project kits', 'url' => '/collections'],
            ['label' => 'Bulk quote', 'url' => '/contact?subject=Bulk+quote'],
        ]);

        $this->menu('plumbing_footer_shop', 'Plumbing — Footer shop', [
            ['label' => 'Pipes', 'url' => '/shop/pipes-fittings'],
            ['label' => 'Fittings', 'url' => '/shop/pvc-fittings'],
            ['label' => 'Valves', 'url' => '/shop/valves'],
            ['label' => 'Faucets', 'url' => '/shop/taps-faucets'],
            ['label' => 'Bathroom', 'url' => '/shop/bathroom'],
            ['label' => 'Pumps', 'url' => '/shop/water-pumps'],
            ['label' => 'Tools', 'url' => '/shop/tools-hardware'],
        ]);

        $this->menu('plumbing_footer_support', 'Plumbing — Footer customer support', [
            ['label' => 'Contact us', 'url' => '/contact'],
            ['label' => 'Track your order', 'url' => '/track-order'],
            ['label' => 'Shipping', 'url' => '/shipping'],
            ['label' => 'Returns', 'url' => '/returns'],
            ['label' => 'FAQs', 'url' => '/faq'],
            ['label' => 'Help center', 'url' => '/help-center'],
        ]);

        $this->menu('plumbing_footer_company', 'Plumbing — Footer company', [
            ['label' => 'About us', 'url' => '/about'],
            ['label' => 'Contact', 'url' => '/contact'],
            ['label' => 'Careers', 'url' => '/careers'],
            ['label' => 'Blog', 'url' => '/journal'],
            ['label' => 'Our stores', 'url' => '/stores'],
        ]);

        $this->menu('plumbing_legal', 'Plumbing — Footer policies', [
            ['label' => 'Privacy policy', 'url' => '/privacy'],
            ['label' => 'Terms & conditions', 'url' => '/terms'],
            ['label' => 'Refund policy', 'url' => '/refund-policy'],
        ]);

        Menu::flush();
    }

    private function menu(string $location, string $name, array $items): void
    {
        $menu = Menu::updateOrCreate(['location' => $location], ['name' => $name, 'template' => 'plumbing']);
        $menu->allItems()->delete();
        foreach ($items as $i => $item) {
            $menu->allItems()->create($item + ['sort_order' => $i]);
        }
    }
}
