<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax master: the taxes a store's products are picked from, e.g. "GST 18%" at 18%. Products
 * point at one (products.tax_id). The client-database copy is database/migrations/client/0001_01_01_000015_create_taxes_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('taxes')) {
            return;
        }

        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('template', 40)->nullable()->index();
            $table->string('name');
            $table->decimal('rate', 5, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
