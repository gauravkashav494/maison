<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A signed-in buyer's Saved items (wishlist): the product ids, newest first. Guests keep theirs
 * on the device only; on sign-in the device list is merged into this one.
 *
 * Customers live in the shared database only, so there is no client-database copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'wishlist')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->json('wishlist')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'wishlist')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('wishlist');
            });
        }
    }
};
