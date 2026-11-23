<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every product store has a brands page (/brands), and new stores get a "Brands" link in their
 * header menu (the template menu seeders). This gives existing stores the same link:
 *  - Plumbing's old "Brands" link (/shop?view=brands) now points at /brands;
 *  - any other product store's header gets a "Brands" item, in the place the seeder puts it,
 *    unless its header already links to /brands.
 * Plumbing Services sells services, not products, and is left alone. The client-database copy is
 * database/migrations/client/0001_01_01_000021_add_brands_to_header_menus.php.
 */
return new class extends Migration
{
    /** Header location => url of the item "Brands" goes after (null: first in the menu). */
    private const HEADERS = [
        'header' => '/collections',            // Fashion
        'grocery_header' => '/collections',    // Grocery: after "Combos"
        'heritage_header' => '/shop/new-arrivals',
        'plumbing_header' => '/shop',          // after "Shop all"
        'bulk_header' => null,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items')) {
            return;
        }

        DB::table('menu_items')->where('url', '/shop?view=brands')->update(['url' => '/brands', 'updated_at' => now()]);

        $withClient = Schema::hasColumn('menu_items', 'client_id') && Schema::hasColumn('menus', 'client_id');
        $columns = $withClient ? ['id', 'location', 'client_id'] : ['id', 'location'];

        foreach (DB::table('menus')->whereIn('location', array_keys(self::HEADERS))->get($columns) as $menu) {
            $items = DB::table('menu_items')->where('menu_id', $menu->id);
            if ((clone $items)->where('url', '/brands')->exists()) {
                continue;
            }

            $after = self::HEADERS[$menu->location];
            $anchor = $after ? (clone $items)->whereNull('parent_id')->where('url', $after)->orderBy('sort_order')->first() : null;
            $position = match (true) {
                $after === null => 0,
                $anchor !== null => $anchor->sort_order + 1,
                default => (int) (clone $items)->whereNull('parent_id')->max('sort_order') + 1, // anchor removed: last
            };

            // Make room: everything at or after the new place moves down one.
            (clone $items)->whereNull('parent_id')->where('sort_order', '>=', $position)->increment('sort_order');

            DB::table('menu_items')->insert(array_filter([
                'menu_id' => $menu->id,
                'parent_id' => null,
                'label' => 'Brands',
                'url' => '/brands',
                'is_accent' => false,
                'opens_in_new_tab' => false,
                'is_active' => true,
                'sort_order' => $position,
                'created_at' => now(),
                'updated_at' => now(),
                'client_id' => $withClient ? $menu->client_id : null,
            ], fn ($value) => $value !== null));
        }

        $this->forgetCachedMenus();
    }

    /** Menu::tree() keeps each header forever, per client ("client:{id}:menu.{location}"). */
    private function forgetCachedMenus(): void
    {
        try {
            $clientIds = DB::connection(config('database.default'))->table('clients')->pluck('id')->all();
        } catch (Throwable) {
            $clientIds = [];
        }

        foreach (array_keys(self::HEADERS) as $location) {
            Cache::forget("menu.{$location}");
            foreach ($clientIds as $id) {
                Cache::forget("client:{$id}:menu.{$location}");
            }
        }
    }

    public function down(): void
    {
        // Leaves the links in place: a store may have moved or renamed it since.
    }
};
