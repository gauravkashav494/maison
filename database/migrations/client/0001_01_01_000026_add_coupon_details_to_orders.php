<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order's promo code details, in a client database. The shared-database copy (and what the
 * columns hold) is database/migrations/2026_11_25_120000_add_coupon_details_to_orders.php.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNS = ['coupon_id', 'coupon_type', 'coupon_value'];

    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            // No foreign key: coupons can sit in another database than orders, and an order
            // keeps its copy of the code after the code itself is removed.
            if (! Schema::hasColumn('orders', 'coupon_id')) {
                $table->unsignedBigInteger('coupon_id')->nullable()->index()->after('discount');
            }
            if (! Schema::hasColumn('orders', 'coupon_type')) {
                $table->string('coupon_type', 20)->nullable()->after('coupon_code');
            }
            if (! Schema::hasColumn('orders', 'coupon_value')) {
                $table->decimal('coupon_value', 12, 2)->nullable()->after('coupon_type');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(self::COLUMNS, fn (string $c) => Schema::hasColumn('orders', $c)));
        if ($columns) {
            Schema::table('orders', function (Blueprint $table) use ($columns) {
                if (in_array('coupon_id', $columns, true)) {
                    $table->dropIndex(['coupon_id']);
                }
                $table->dropColumn($columns);
            });
        }
    }
};
