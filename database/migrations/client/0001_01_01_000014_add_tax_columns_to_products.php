<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A product's tax columns, in a client database. The shared-database copy (and what each
 * column holds) is database/migrations/2026_11_17_130000_add_tax_columns_to_products.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        // One column at a time: a database may already have some of them.
        $columns = [
            'tax_id' => fn (Blueprint $table) => $table->unsignedBigInteger('tax_id')->nullable()->index()->after('compare_at_price'),
            'tax_rate' => fn (Blueprint $table) => $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_id'),
            'price_incl_tax' => fn (Blueprint $table) => $table->decimal('price_incl_tax', 12, 2)->nullable()->after('tax_rate'),
            'price_excl_tax' => fn (Blueprint $table) => $table->decimal('price_excl_tax', 12, 2)->nullable()->after('price_incl_tax'),
        ];
        foreach ($columns as $column => $add) {
            if (! Schema::hasColumn('products', $column)) {
                Schema::table('products', $add);
            }
        }

        // Variants follow their product's tax: drop any rate a variant row had of its own.
        DB::table('products')->whereNotNull('variants')->where('variants', 'like', '%tax_rate%')->orderBy('id')
            ->each(function ($product) {
                $variants = json_decode($product->variants, true);
                if (! is_array($variants)) {
                    return;
                }
                $variants = array_map(fn ($row) => is_array($row) ? array_diff_key($row, ['tax_rate' => true]) : $row, $variants);
                DB::table('products')->where('id', $product->id)->update(['variants' => json_encode($variants)]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'tax_id')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropIndex(['tax_id']));
        }
        $columns = array_values(array_filter(
            ['tax_id', 'tax_rate', 'price_incl_tax', 'price_excl_tax'],
            fn (string $column) => Schema::hasColumn('products', $column),
        ));
        if ($columns !== []) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
