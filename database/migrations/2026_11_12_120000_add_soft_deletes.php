<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting these rows archives them (deleted_at) instead of removing them, so a wrong click
 * can be undone and old orders keep pointing at real products and customers. The content
 * tables are listed too: in a shared database they live here; client databases get the
 * same column from database/migrations/client.
 */
return new class extends Migration
{
    private const TABLES = [
        // Platform
        'users', 'clients', 'client_users', 'client_roles', 'customers', 'plans',
        // Content (shared database; client databases get the same from database/migrations/client)
        'products', 'categories', 'collections', 'services', 'orders', 'order_items', 'coupons',
        'pages', 'posts', 'menus', 'projects', 'service_areas', 'reviews', 'faqs', 'testimonials',
        'contact_messages', 'service_requests', 'subscribers',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->softDeletes()->index());
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
            }
        }
    }
};
