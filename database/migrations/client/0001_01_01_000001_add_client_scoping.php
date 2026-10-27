<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The same client/customer columns the main database has, inside a client database.
 *
 * A client database holds one client's rows, so the client id is not what separates them
 * there — but it is still written and still scoped, so content copied between installations
 * (or a client later merged back into the shared database) keeps its owner, and a
 * misconfigured connection cannot hand another client's rows to a query.
 */
return new class extends Migration
{
    public const CONTENT_TABLES = [
        'products', 'categories', 'collections', 'coupons', 'orders', 'reviews',
        'pages', 'posts', 'faqs', 'menus', 'menu_items', 'contact_messages', 'subscribers',
        'services', 'service_areas', 'testimonials', 'projects', 'service_requests',
    ];

    /** Customers live in the platform database: these are plain ids, never foreign keys. */
    public const CUSTOMER_TABLES = ['orders', 'reviews', 'service_requests'];

    public function up(): void
    {
        foreach (self::CONTENT_TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'client_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('client_id')->nullable()->index());
            }
        }

        foreach (self::CUSTOMER_TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'customer_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('customer_id')->nullable()->index());
            }
        }

        // The same per-client unique keys the main database has, so a client database can be
        // merged back into a shared one without a key suddenly colliding.
        foreach (self::UNIQUE_KEYS as [$table, $columns, $composite]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'client_id')) {
                continue;
            }
            $indexes = collect(Schema::getIndexes($table));
            $name = $table.'_'.implode('_', $composite).'_unique';
            if ($indexes->contains(fn (array $index) => ($index['name'] ?? null) === $name)) {
                continue;
            }
            $existing = $indexes->first(fn (array $index) => ! empty($index['unique']) && ($index['columns'] ?? []) === $columns);
            if ($existing) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($existing['name']));
            }
            Schema::table($table, fn (Blueprint $t) => $t->unique($composite, $name));
        }
    }

    /** [table, current unique columns, columns with the client added] */
    public const UNIQUE_KEYS = [
        ['products', ['slug'], ['client_id', 'slug']],
        ['products', ['sku'], ['client_id', 'sku']],
        ['categories', ['slug'], ['client_id', 'slug']],
        ['collections', ['slug'], ['client_id', 'slug']],
        ['coupons', ['code'], ['client_id', 'code']],
        ['pages', ['slug', 'storefront_template'], ['client_id', 'storefront_template', 'slug']],
        ['posts', ['slug', 'template'], ['client_id', 'template', 'slug']],
        ['menus', ['location'], ['client_id', 'location']],
        ['services', ['template', 'slug'], ['client_id', 'template', 'slug']],
        ['service_areas', ['template', 'slug'], ['client_id', 'template', 'slug']],
        ['projects', ['template', 'slug'], ['client_id', 'template', 'slug']],
        ['subscribers', ['email'], ['client_id', 'email']],
        ['orders', ['number'], ['client_id', 'number']],
        ['service_requests', ['reference'], ['client_id', 'reference']],
    ];

    public function down(): void
    {
        foreach (self::CONTENT_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'client_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('client_id'));
            }
        }
        foreach (self::CUSTOMER_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'customer_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('customer_id'));
            }
        }
    }
};
