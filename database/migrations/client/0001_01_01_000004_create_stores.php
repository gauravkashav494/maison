<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store locations: the client's own shops, listed on its "Our stores" page. They used to be
 * one platform-wide list; the main migration 2026_11_12_130000_move_store_locations_to_clients
 * copied that list into every existing client (and already created this table for them).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stores')) {
            return;
        }

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('country')->default('India');
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('hours')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('image')->nullable();
            $table->string('maps_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('template', 40)->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
