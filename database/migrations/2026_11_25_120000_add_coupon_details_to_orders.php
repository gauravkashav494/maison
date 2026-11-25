<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The promo code on an order as it was when the order was placed: which code (coupon_id, next
 * to the existing coupon_code), its type (percent, fixed, free_shipping) and its value (10 for
 * 10%, 200 for ₹200 off). The order page and invoice say "10% off" from these, so editing or
 * removing the code later does not change what an issued invoice says. Orders placed before
 * this have none. The client-database copy is
 * database/migrations/client/0001_01_01_000026_add_coupon_details_to_orders.php.
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
