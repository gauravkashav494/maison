<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A product may sit in several categories, in a client database. The shared-database copy is
 * database/migrations/2026_11_17_120000_create_category_product_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            return;
        }

        if (! Schema::hasTable('category_product')) {
            Schema::create('category_product', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('product_id');
                $table->primary(['category_id', 'product_id']);
                $table->index('product_id');
                $table->foreign('category_id', 'category_product_category_id_foreign')->references('id')->on('categories')->cascadeOnDelete();
                $table->foreign('product_id', 'category_product_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        DB::table('category_product')->insertUsing(
            ['category_id', 'product_id'],
            DB::table('products')->whereNotNull('category_id')
                ->whereIn('category_id', DB::table('categories')->select('id'))
                ->whereNotIn('id', DB::table('category_product')->select('product_id'))
                ->select('category_id', 'id'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};
