<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two-way sync with a client's local system: the agent tokens, and the columns that record
 * what the agent has already taken. The content columns have a twin in
 * database/migrations/client/0001_01_01_000003_add_sync_columns.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        foreach (['products', 'orders', 'customers'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('external_id', 191)->nullable();
                $table->unsignedBigInteger('sync_version')->default(1);
                $table->unsignedBigInteger('sync_acked_version')->default(0);
                $table->unique(['client_id', 'external_id']);
            });
        }

        // Orders placed before sync existed were never meant for the local system, and an
        // unacknowledged order is held back from stock, so they start out as already taken.
        DB::table('orders')->update(['sync_acked_version' => 1]);
    }

    public function down(): void
    {
        foreach (['products', 'orders', 'customers'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropUnique($name.'_client_id_external_id_unique');
                $table->dropColumn(['external_id', 'sync_version', 'sync_acked_version']);
            });
        }

        Schema::dropIfExists('sync_tokens');
    }
};
