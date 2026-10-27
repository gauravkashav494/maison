<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Moves the existing single-tenant data onto the client model without losing anything.
 *
 *   - one client per storefront, inheriting that store's limits;
 *   - every Store Owner account becomes that client's Client Admin (same name, email and
 *     password hash) and is then removed from `users`, which now holds super admins only;
 *   - every content row is stamped with its client (resolved from its template);
 *   - accounts that placed orders, wrote reviews, booked a service or saved an address become
 *     `customers` of the client whose store they used; the historic user_id is left in place.
 *
 * Idempotent: re-running changes nothing. Nothing is deleted before its replacement exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients') || ! Schema::hasTable('storefronts')) {
            return;
        }

        $planId = $this->defaultPlan();
        $clients = $this->clientsForStorefronts($planId);          // template => client id
        $this->moveStoreOwners();
        $this->stampContent($clients);
        $this->createCustomers($clients);
    }

    public function down(): void
    {
        // Reversing would destroy the accounts this created; the schema migration drops the tables.
    }

    private function defaultPlan(): ?int
    {
        if (! Schema::hasTable('plans')) {
            return null;
        }
        $existing = DB::table('plans')->where('slug', 'starter')->value('id');
        if ($existing) {
            return $existing;
        }

        return DB::table('plans')->insertGetId([
            'name' => 'Starter',
            'slug' => 'starter',
            'price_label' => '₹X',
            'product_limit' => null,
            'image_limit' => 50,
            'storage_limit_mb' => null,
            'is_default' => true,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string,int> template id => client id */
    private function clientsForStorefronts(?int $planId): array
    {
        $map = [];

        foreach (DB::table('storefronts')->orderBy('id')->get() as $store) {
            $client = DB::table('clients')->where('slug', $store->slug)->first();

            if (! $client) {
                $id = DB::table('clients')->insertGetId([
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'storefront_id' => $store->id,
                    'plan_id' => $planId,
                    'domain' => $store->domain ?: null,
                    'is_active' => $store->is_active,
                    'product_limit' => $store->product_limit,
                    'image_limit' => null, // inherit the plan's 50
                    'storage_limit_mb' => $store->storage_limit_mb,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $id = $client->id;
            }

            $map[$store->template] = $id;
        }

        return $map;
    }

    /** Store Owner accounts become Client Admins, then leave the super-admin table. */
    private function moveStoreOwners(): void
    {
        if (! Schema::hasTable('client_users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        $moved = [];

        foreach (DB::table('users')->whereNotNull('role')->where('role', '!=', 'super_admin')->get() as $user) {
            $clientId = $user->storefront_id
                ? DB::table('clients')->where('storefront_id', $user->storefront_id)->value('id')
                : null;
            if (! $clientId) {
                continue; // nothing to attach the account to: leave it untouched for review
            }

            $exists = DB::table('client_users')->where('client_id', $clientId)->where('email', $user->email)->exists();
            if (! $exists) {
                DB::table('client_users')->insert([
                    'client_id' => $clientId,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'email_verified_at' => $user->email_verified_at,
                    'password' => $user->password,
                    'role' => 'client_admin',
                    'is_active' => $user->is_active ?? true,
                    'remember_token' => null,
                    'created_at' => $user->created_at ?: now(),
                    'updated_at' => now(),
                ]);
            }

            $moved[] = $user->id;
        }

        if ($moved) {
            DB::table('users')->whereIn('id', $moved)->delete();
        }
    }

    /** Stamp each content row with the client that owns its template. */
    private function stampContent(array $clients): void
    {
        foreach (['products', 'categories', 'collections', 'coupons', 'orders', 'reviews',
            'pages', 'posts', 'faqs', 'menus', 'menu_items', 'contact_messages', 'subscribers',
            'services', 'service_areas', 'testimonials', 'projects', 'service_requests'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'client_id')) {
                continue;
            }
            // Pages carry both a layout `template` and the owning `storefront_template`; the
            // storefront column always wins.
            $column = Schema::hasColumn($table, 'storefront_template') ? 'storefront_template'
                : (Schema::hasColumn($table, 'template') ? 'template' : null);
            if (! $column) {
                continue;
            }
            foreach ($clients as $template => $clientId) {
                DB::table($table)->whereNull('client_id')->where($column, $template)->update(['client_id' => $clientId]);
            }
        }

        // menu_items have no template column of their own: they follow the menu they belong to.
        if (Schema::hasTable('menu_items') && Schema::hasColumn('menu_items', 'client_id')) {
            foreach (DB::table('menus')->whereNotNull('client_id')->get(['id', 'client_id']) as $menu) {
                DB::table('menu_items')->whereNull('client_id')->where('menu_id', $menu->id)->update(['client_id' => $menu->client_id]);
            }
        }
    }

    /**
     * Storefront accounts that own content become customers of the client whose store they used.
     * The same address may exist under two clients — they are separate accounts by design.
     */
    private function createCustomers(array $clients): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $byClientAndUser = [];   // [clientId][userId] => customer id
        $sources = [
            'orders' => 'client_id',
            'reviews' => 'client_id',
            'service_requests' => 'client_id',
        ];

        foreach ($sources as $table => $clientColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'customer_id') || ! Schema::hasColumn($table, 'user_id')) {
                continue;
            }
            $rows = DB::table($table)
                ->whereNotNull('user_id')
                ->whereNull('customer_id')
                ->whereNotNull($clientColumn)
                ->select('user_id', $clientColumn)
                ->distinct()
                ->get();

            foreach ($rows as $row) {
                $customerId = $byClientAndUser[$row->client_id][$row->user_id]
                    ??= $this->customerFor((int) $row->client_id, (int) $row->user_id);
                if (! $customerId) {
                    continue;
                }
                DB::table($table)
                    ->whereNull('customer_id')
                    ->where('user_id', $row->user_id)
                    ->where($clientColumn, $row->client_id)
                    ->update(['customer_id' => $customerId]);
            }
        }

        // Addresses carry no store of their own: they follow the client the account ordered from,
        // or the first client when there are no orders.
        if (Schema::hasTable('addresses') && Schema::hasColumn('addresses', 'customer_id')) {
            foreach (DB::table('addresses')->whereNull('customer_id')->whereNotNull('user_id')->distinct()->pluck('user_id') as $userId) {
                $clientId = Schema::hasTable('orders')
                    ? DB::table('orders')->where('user_id', $userId)->whereNotNull('client_id')->value('client_id')
                    : null;
                $clientId ??= reset($clients) ?: null;
                if (! $clientId) {
                    continue;
                }
                $customerId = $byClientAndUser[$clientId][$userId] ??= $this->customerFor((int) $clientId, (int) $userId);
                if ($customerId) {
                    DB::table('addresses')->whereNull('customer_id')->where('user_id', $userId)->update(['customer_id' => $customerId]);
                }
            }
        }
    }

    private function customerFor(int $clientId, int $userId): ?int
    {
        $user = DB::table('users')->find($userId);
        if (! $user) {
            return null;
        }

        $existing = DB::table('customers')->where('client_id', $clientId)->where('email', $user->email)->value('id');
        if ($existing) {
            return $existing;
        }

        return DB::table('customers')->insertGetId([
            'client_id' => $clientId,
            'name' => $user->name ?: 'Customer',
            'email' => $user->email ?: Str::uuid().'@example.invalid',
            'phone' => $user->phone,
            'email_verified_at' => $user->email_verified_at,
            'password' => $user->password,
            'is_active' => true,
            'created_at' => $user->created_at ?: now(),
            'updated_at' => now(),
        ]);
    }
};
