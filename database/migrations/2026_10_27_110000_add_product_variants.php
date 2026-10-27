<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product variants with flexible attributes.
 *
 *   has_variants         variants are switched on for this product
 *   variant_attributes   the attributes it varies by: [{name, values[], swatches{}}]
 *   variants             the combinations: [{options{}, sku, price, stock, is_active}]
 *
 * The existing `colors` and `sizes` columns are kept and keep working: a product that uses
 * them is read as having a Colour and a Size attribute, so nothing on the storefront changes
 * until variants are switched on for that product. The backfill below copies those lists into
 * variant_attributes so the admin sees them in the new form straight away.
 *
 * Order lines gain the selected options and the variant's SKU, so what the customer chose is
 * recorded with the order rather than inferred later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'has_variants')) {
                $table->boolean('has_variants')->default(false)->after('sizes');
            }
            if (! Schema::hasColumn('products', 'variant_attributes')) {
                $table->json('variant_attributes')->nullable()->after('has_variants');
            }
            if (! Schema::hasColumn('products', 'variants')) {
                $table->json('variants')->nullable()->after('variant_attributes');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'options')) {
                $table->json('options')->nullable()->after('color');
            }
            if (! Schema::hasColumn('order_items', 'variant_sku')) {
                $table->string('variant_sku')->nullable()->after('options');
            }
        });

        $this->backfillAttributes();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['has_variants', 'variant_attributes', 'variants'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            foreach (['options', 'variant_sku'] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /** Describe each product's existing colours and sizes as variant attributes. */
    private function backfillAttributes(): void
    {
        if (! Schema::hasColumn('products', 'colors')) {
            return;
        }

        DB::table('products')->whereNull('variant_attributes')->orderBy('id')->chunk(200, function ($products) {
            foreach ($products as $product) {
                $attributes = [];

                $colours = json_decode((string) $product->colors, true) ?: [];
                $values = [];
                $swatches = [];
                foreach ($colours as $colour) {
                    $name = is_array($colour) ? ($colour['name'] ?? null) : $colour;
                    if (! $name) {
                        continue;
                    }
                    $values[] = $name;
                    if (is_array($colour) && ! empty($colour['hex'])) {
                        $swatches[$name] = $colour['hex'];
                    }
                }
                if ($values) {
                    $attributes[] = ['name' => 'Colour', 'values' => $values, 'swatches' => $swatches];
                }

                $sizes = json_decode((string) ($product->sizes ?? ''), true) ?: [];
                $sizes = array_values(array_filter(array_map('strval', $sizes), 'strlen'));
                if ($sizes) {
                    $attributes[] = ['name' => 'Size', 'values' => $sizes, 'swatches' => []];
                }

                if ($attributes) {
                    DB::table('products')->where('id', $product->id)->update(['variant_attributes' => json_encode($attributes)]);
                }
            }
        });
    }
};
