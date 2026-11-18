<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each order line's tax, as charged when the order was placed: the product's rate and the tax
 * on the line (after its share of any discount). Lines may differ in rate, so the invoice lists
 * the tax per rate. Older lines leave both empty and use the order's single rate. The
 * client-database copy is database/migrations/client/0001_01_01_000016_add_tax_to_order_items.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        foreach ([
            'tax_rate' => fn (Blueprint $table) => $table->decimal('tax_rate', 5, 2)->nullable()->after('total'),
            'tax' => fn (Blueprint $table) => $table->decimal('tax', 12, 2)->nullable()->after('tax_rate'),
        ] as $column => $add) {
            if (! Schema::hasColumn('order_items', $column)) {
                Schema::table('order_items', $add);
            }
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter(['tax_rate', 'tax'], fn (string $column) => Schema::hasColumn('order_items', $column)));
        if ($columns !== []) {
            Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
