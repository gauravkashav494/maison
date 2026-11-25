<?php

namespace Database\Seeders;

use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Navigation for the Bulk Order template. Its own menu locations, so no other
 * template's menus are touched. Re-running resets only these menus' items.
 */
class BulkMenuSeeder extends Seeder
{
    public function run(): void
    {
        // Shown at the right of the category bar; the categories themselves come from the catalogue.
        $this->menu('bulk_header', 'Bulk — Header links', [
            ['label' => 'Brands', 'url' => '/brands'],
            ['label' => 'Track order', 'url' => '/track-order'],
            ['label' => 'Contact', 'url' => '/contact'],
        ]);

        // The footer's Shop column.
        $this->menu('bulk_footer_shop', 'Bulk — Footer shop', [
            ['label' => 'Bulk order', 'url' => '/#bulk-order'],
            ['label' => 'All products', 'url' => '/shop'],
            ['label' => 'Brands', 'url' => '/brands'],
            ['label' => 'Cleaning Chemicals', 'url' => '/shop/cleaning'],
            ['label' => 'Packaging & Disposables', 'url' => '/shop/disposables'],
            ['label' => 'Gloves & Hygiene', 'url' => '/shop/hygiene'],
            ['label' => 'Kitchen Consumables', 'url' => '/shop/kitchen'],
            ['label' => 'Office Supplies', 'url' => '/shop/office'],
        ]);

        // The footer's Help column.
        $this->menu('bulk_footer', 'Bulk — Footer help', [
            ['label' => 'My orders', 'url' => '/account/orders'],
            ['label' => 'My account', 'url' => '/account'],
            ['label' => 'Track order', 'url' => '/track-order'],
            ['label' => 'Contact us', 'url' => '/contact'],
        ]);

        $this->menu('bulk_legal', 'Bulk — Footer policies', [
            ['label' => 'Privacy policy', 'url' => '/privacy'],
            ['label' => 'Terms & conditions', 'url' => '/terms'],
            ['label' => 'Refund policy', 'url' => '/refund-policy'],
        ]);

        Menu::flush();
    }

    private function menu(string $location, string $name, array $items): void
    {
        $menu = Menu::updateOrCreate(
            // clients:provision opens the client's database without resolving the context: take the
            // client from the open database then, or a re-run makes a second set of menus.
            ['location' => $location, 'client_id' => app(ClientContext::class)->id() ?? app(ClientDatabase::class)->current()?->id],
            ['name' => $name, 'template' => 'bulk'],
        );
        $menu->allItems()->delete();
        foreach ($items as $i => $item) {
            $menu->allItems()->create($item + ['sort_order' => $i]);
        }
    }
}
