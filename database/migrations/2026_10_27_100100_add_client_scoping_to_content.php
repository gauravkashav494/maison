<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every content row an owning client, and points customer-owned rows at the new
 * `customers` table instead of `users`.
 *
 * The client id is what keeps two clients running the same store apart. With per-client
 * databases the rows are already in different databases; the column is still written and
 * still scoped, so a single-database install is isolated too and a misconfigured connection
 * cannot leak rows.
 *
 * No foreign key is added for client_id: these tables also live in per-client databases,
 * where `clients` is in another database. Isolation is enforced by the global scope, the
 * policies and the resolved connection, all server-side.
 */
return new class extends Migration
{
    /** Store content: every row belongs to exactly one client. */
    public const CONTENT_TABLES = [
        'products', 'categories', 'collections', 'coupons', 'orders', 'reviews',
        'pages', 'posts', 'faqs', 'menus', 'menu_items', 'contact_messages', 'subscribers',
        'services', 'service_areas', 'testimonials', 'projects', 'service_requests',
    ];

    /** Rows owned by a storefront account: user_id becomes customer_id. */
    public const CUSTOMER_TABLES = ['orders', 'reviews', 'service_requests', 'addresses'];

    public function up(): void
    {
        foreach (self::CONTENT_TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'client_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('client_id')->nullable()->index();
            });
        }

        foreach (self::CUSTOMER_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'customer_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('customer_id')->nullable()->index();
                });
            }
            // The old foreign key points at users, which now holds super admins only; dropping it
            // keeps the historic user_id values readable without tying content to a staff account.
            $this->dropForeignKeyTo($table, 'user_id', 'users');
        }
    }

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

    private function dropForeignKeyTo(string $table, string $column, string $target): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        foreach (Schema::getForeignKeys($table) as $key) {
            if (($key['foreign_table'] ?? null) !== $target || ! in_array($column, $key['columns'] ?? [], true) || ! ($key['name'] ?? null)) {
                continue;
            }
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($key['name']));
        }
    }
};
