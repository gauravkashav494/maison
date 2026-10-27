<?php

namespace Database\Seeders;

use App\Clients\ClientContext;
use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Navigation for the Simple List template. Its own menu locations, so no other
 * template's menus are touched. Re-running resets only these menus' items.
 */
class SimpleMenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->menu('simple_header', 'Simple — Header navigation', [
            ['label' => 'Order list', 'url' => '/shop'],
            ['label' => 'Track order', 'url' => '/track-order'],
            ['label' => 'Contact', 'url' => '/contact'],
        ]);

        $this->menu('simple_footer', 'Simple — Footer links', [
            ['label' => 'Order list', 'url' => '/shop'],
            ['label' => 'My account', 'url' => '/account'],
            ['label' => 'Track order', 'url' => '/track-order'],
            ['label' => 'Contact', 'url' => '/contact'],
        ]);

        $this->menu('simple_legal', 'Simple — Footer policies', [
            ['label' => 'Privacy policy', 'url' => '/privacy'],
            ['label' => 'Terms & conditions', 'url' => '/terms'],
            ['label' => 'Refund policy', 'url' => '/refund-policy'],
        ]);

        Menu::flush();
    }

    private function menu(string $location, string $name, array $items): void
    {
        $menu = Menu::updateOrCreate(
            ['location' => $location, 'client_id' => app(ClientContext::class)->id()],
            ['name' => $name, 'template' => 'simple'],
        );
        $menu->allItems()->delete();
        foreach ($items as $i => $item) {
            $menu->allItems()->create($item + ['sort_order' => $i]);
        }
    }
}
