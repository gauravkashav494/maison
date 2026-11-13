<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review moderation columns. In a shared database the reviews table lives here; client
 * databases get the same from database/migrations/client.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews') || Schema::hasColumn('reviews', 'is_verified_purchase')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            // The reviewer was signed in and has a delivered order containing this product.
            $table->boolean('is_verified_purchase')->default(false)->after('is_approved');
            // Why a review is waiting for approval (a link, a phone number, not a verified buyer…).
            $table->string('held_reason', 160)->nullable()->after('is_verified_purchase');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('reviews', 'is_verified_purchase')) {
            Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn(['is_verified_purchase', 'held_reason']));
        }
    }
};
