<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps a client database level with the main one: product variants and the manual
 * fulfilment fields. The same columns the ordinary migrations add to the platform database
 * (2026_10_27_110000 and 2026_10_27_120000).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'has_variants')) {
                    $table->boolean('has_variants')->default(false);
                }
                if (! Schema::hasColumn('products', 'variant_attributes')) {
                    $table->json('variant_attributes')->nullable();
                }
                if (! Schema::hasColumn('products', 'variants')) {
                    $table->json('variants')->nullable();
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'options')) {
                    $table->json('options')->nullable();
                }
                if (! Schema::hasColumn('order_items', 'variant_sku')) {
                    $table->string('variant_sku')->nullable();
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['dispatched_at', 'delivered_at', 'cancelled_at'] as $column) {
                    if (! Schema::hasColumn('orders', $column)) {
                        $table->timestamp($column)->nullable();
                    }
                }
                if (! Schema::hasColumn('orders', 'cancellation_reason')) {
                    $table->string('cancellation_reason', 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'products' => ['has_variants', 'variant_attributes', 'variants'],
            'order_items' => ['options', 'variant_sku'],
            'orders' => ['dispatched_at', 'delivered_at', 'cancelled_at', 'cancellation_reason'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
        }
    }
};
