<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review moderation columns for a client database. The shared-database copy is
 * database/migrations/2026_11_13_100000_add_review_moderation.php.
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
