<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records on each order whether its prices included tax. The client-database copy is
 * database/migrations/client/0001_01_01_000007_add_tax_mode_to_orders.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'prices_include_tax')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            // How tax applied when the order was placed, so later setting changes never rewrite it.
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax');
            $table->string('tax_label', 40)->nullable()->after('tax_rate');
            // false: prices were before tax and the tax was added on top (B2B).
            // true: prices already included the tax; the tax figure is the part inside them.
            $table->boolean('prices_include_tax')->default(false)->after('tax_label');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'prices_include_tax')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['tax_rate', 'tax_label', 'prices_include_tax']));
        }
    }
};
