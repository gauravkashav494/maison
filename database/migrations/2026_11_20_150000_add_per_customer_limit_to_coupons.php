<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many times one customer may use a coupon (null = no limit), checked when the order is placed
 * (Coupon::isUsableBy). The client-database copy is
 * database/migrations/client/0001_01_01_000023_add_per_customer_limit_to_coupons.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons') || Schema::hasColumn('coupons', 'per_customer_limit')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('per_customer_limit')->nullable()->after('usage_limit');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('coupons', 'per_customer_limit')) {
            Schema::table('coupons', fn (Blueprint $table) => $table->dropColumn('per_customer_limit'));
        }
    }
};
