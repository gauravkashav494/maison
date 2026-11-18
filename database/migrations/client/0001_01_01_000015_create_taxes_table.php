<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax master, in a client database. The shared-database copy (and what it holds) is
 * database/migrations/2026_11_17_160000_create_taxes_table.php.
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
