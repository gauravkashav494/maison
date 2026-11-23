<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many times one customer may use a coupon, in a client database. The shared-database copy is
 * database/migrations/2026_11_20_150000_add_per_customer_limit_to_coupons.php.
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
