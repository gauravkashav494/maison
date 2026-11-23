<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory: the stock history (every move in or out of a product's stock, and why) and
 * products.lowest_stock, the stock of the product's lowest variant (or its own stock), which
 * the Stock levels screen filters and sorts on.
 *
 * The client-database copy is database/migrations/client/0001_01_01_000018_create_stock_movements_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->string('template', 40)->nullable()->index();
                // No foreign keys: products and orders may live in a client database.
                $table->unsignedBigInteger('product_id')->index();
                $table->string('product_name');
                $table->string('variant')->nullable();
                $table->json('options')->nullable();
                $table->integer('quantity');
                $table->integer('stock_after');
                $table->string('reason', 20)->index();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('order_number', 40)->nullable();
                $table->string('by_name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('products', 'lowest_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('lowest_stock')->nullable()->index();
            });
        }

        // Fill lowest_stock in for existing products (App\Models\Product::lowestStock() keeps it after).
        DB::table('products')->whereNull('lowest_stock')->select('id', 'stock', 'has_variants', 'variants')->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $stocks = [];
                    foreach ($row->has_variants ? (array) json_decode((string) $row->variants, true) : [] as $variant) {
                        if (is_array($variant) && array_filter((array) ($variant['options'] ?? []), 'filled') && ($variant['is_active'] ?? true)) {
                            $stock = $variant['stock'] ?? null;
                            $stocks[] = $stock === null || $stock === '' ? (int) $row->stock : (int) $stock;
                        }
                    }
                    DB::table('products')->where('id', $row->id)->update(['lowest_stock' => $stocks ? min($stocks) : (int) $row->stock]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'lowest_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['lowest_stock']);
                $table->dropColumn('lowest_stock');
            });
        }
        Schema::dropIfExists('stock_movements');
    }
};
