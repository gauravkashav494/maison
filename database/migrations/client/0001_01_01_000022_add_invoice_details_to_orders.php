<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an order's tax invoice keeps once issued, so later changes never rewrite it:
 *
 * - seller: the seller's details as printed (business name, address, GSTIN, state, contact,
 *   note), copied from Site settings when the order is placed. Older orders leave it empty and
 *   read the settings live.
 * - invoiced_at: when the invoice was issued, on dispatch or when a sales order is placed at
 *   the counter (Order::assignInvoiceNumber). Printed as the invoice date; the order date stays
 *   the day it was placed. Orders numbered before this column read their order date instead.
 *
 * In a client database. The other copy is database/migrations/2026_11_20_140000_add_invoice_details_to_orders.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        foreach ([
            'seller' => fn (Blueprint $table) => $table->json('seller')->nullable(),
            'invoiced_at' => fn (Blueprint $table) => $table->timestamp('invoiced_at')->nullable(),
        ] as $column => $add) {
            if (! Schema::hasColumn('orders', $column)) {
                Schema::table('orders', $add);
            }
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter(['seller', 'invoiced_at'], fn (string $column) => Schema::hasColumn('orders', $column)));
        if ($columns !== []) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
