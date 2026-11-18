<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A product may sit in several categories. products.category_id stays as the product's main
 * category (breadcrumb, card label); category_product holds every category it is listed under,
 * the main one included. The client-database copy is
 * database/migrations/client/0001_01_01_000011_create_category_product_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_product')) {
            Schema::create('category_product', function (Blueprint $table) {
                $table->foreignId('category_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->primary(['category_id', 'product_id']);
                $table->index('product_id');
            });
        }

        // Every product starts out listed under the category it already has.
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
