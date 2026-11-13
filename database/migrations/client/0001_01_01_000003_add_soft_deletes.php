<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft deletes for a client database's content (same list as the main database migration).
 */
return new class extends Migration
{
    private const TABLES = [
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
