<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order line's HSN code, in a client database. The shared-database copy (and what the
 * column holds) is database/migrations/2026_11_25_110000_add_hsn_code_to_order_items.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'hsn_code')) {
            Schema::table('order_items', fn (Blueprint $table) => $table->string('hsn_code', 8)->nullable()->after('sku'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'hsn_code')) {
            Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('hsn_code'));
        }
    }
};
