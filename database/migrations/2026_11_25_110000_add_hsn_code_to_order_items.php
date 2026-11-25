<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The HSN code of the product on an order line, copied when the order is placed, so the
 * invoice keeps the code the goods were sold under even if the product is changed later.
 * Lines placed before this have none. The client-database copy is
 * database/migrations/client/0001_01_01_000025_add_hsn_code_to_order_items.php.
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
