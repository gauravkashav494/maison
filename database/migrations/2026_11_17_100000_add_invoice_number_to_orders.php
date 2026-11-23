<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives each order its own invoice number. The client-database copy is
 * database/migrations/client/0001_01_01_000010_add_invoice_number_to_orders.php.
 *
 * Invoice numbers run in one unbroken series per store and financial year (April–March),
 * e.g. INV/26-27/0001, as GST asks; see Order::assignInvoiceNumber().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'invoice_number')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_number', 16)->nullable()->after('number');
            $table->string('invoice_fy', 5)->nullable()->after('invoice_number');
            $table->unsignedInteger('invoice_seq')->nullable()->after('invoice_fy');
            // One number per store and year; also stops two orders taking the same number at once.
            $table->unique(['client_id', 'invoice_fy', 'invoice_seq'], 'orders_invoice_series_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'invoice_number')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique('orders_invoice_series_unique');
                $table->dropColumn(['invoice_number', 'invoice_fy', 'invoice_seq']);
            });
        }
    }
};
