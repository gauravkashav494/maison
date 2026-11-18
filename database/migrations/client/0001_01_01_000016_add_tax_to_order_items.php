<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order line tax, in a client database. The shared-database copy (and what it holds) is
 * database/migrations/2026_11_17_170000_add_tax_to_order_items.php.
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
