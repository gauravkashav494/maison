<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses are owned by `customer_id` since the client split; `user_id` is only kept so
 * historic rows stay readable. It was created NOT NULL, which blocks saving a customer's
 * address at checkout, so it becomes nullable like the other legacy user_id columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('addresses') || ! Schema::hasColumn('addresses', 'user_id')) {
            return;
        }

        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversed: rows saved by customers have no user_id, so restoring NOT NULL would fail.
    }
};
