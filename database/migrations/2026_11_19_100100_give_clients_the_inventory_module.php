<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Inventory module (config/client_modules.php) is new, so existing accounts are given it:
 *  - a client with a chosen list of modules gets "inventory" added (a null list already has every module);
 *  - a role that could edit products, and so already change stock on the product form, may now
 *    view and adjust stock in Inventory; a role that could only view products may view it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'modules')) {
            foreach (DB::table('clients')->whereNotNull('modules')->get(['id', 'modules']) as $client) {
                $modules = (array) json_decode((string) $client->modules, true);
                if (! in_array('inventory', $modules, true)) {
                    $modules[] = 'inventory';
                    DB::table('clients')->where('id', $client->id)->update(['modules' => json_encode(array_values($modules))]);
                }
            }
        }

        if (Schema::hasTable('client_roles')) {
            foreach (DB::table('client_roles')->get(['id', 'permissions']) as $role) {
                $permissions = (array) json_decode((string) $role->permissions, true);
                $add = match (true) {
                    in_array('products.update', $permissions, true) => ['inventory.view', 'inventory.update'],
                    in_array('products.view', $permissions, true) => ['inventory.view'],
                    default => [],
                };
                $new = array_values(array_unique([...$permissions, ...$add]));
                if ($new !== $permissions) {
                    DB::table('client_roles')->where('id', $role->id)->update(['permissions' => json_encode($new)]);
                }
            }
        }
    }

    public function down(): void
    {
        // Leaves the grants in place: removing them could take away access set by hand since.
    }
};
