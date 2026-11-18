<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sales person who placed an order (Customer::isSalesperson()): set on every sales order,
 * whether it was placed for the sales person or for a customer they were serving. Null for
 * orders customers placed themselves. The other copy is database/migrations/client/0001_01_01_000017_add_salesperson_to_orders.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'salesperson_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            // A customers.id in the platform database (no foreign key: orders may live in a client database).
            $table->unsignedBigInteger('salesperson_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'salesperson_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['salesperson_id']);
                $table->dropColumn('salesperson_id');
            });
        }
    }
};
