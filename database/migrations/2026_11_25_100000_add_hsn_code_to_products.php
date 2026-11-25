<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product's HSN code (Harmonized System of Nomenclature): the 4, 6 or 8 digit GST
 * classification of the goods. Optional. The client-database copy is
 * database/migrations/client/0001_01_01_000024_add_hsn_code_to_products.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'hsn_code')) {
            Schema::table('products', fn (Blueprint $table) => $table->string('hsn_code', 8)->nullable()->after('tax_id'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'hsn_code')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('hsn_code'));
        }
    }
};
