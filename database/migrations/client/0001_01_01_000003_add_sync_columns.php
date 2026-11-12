<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps a client database level with the main one: the sync columns on its content tables
 * (2026_11_10_100000_create_sync_support). Customers live in the main database only.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'orders'] as $name) {
            if (! Schema::hasTable($name) || Schema::hasColumn($name, 'external_id')) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) {
                $table->string('external_id', 191)->nullable();
                $table->unsignedBigInteger('sync_version')->default(1);
                $table->unsignedBigInteger('sync_acked_version')->default(0);
                $table->unique(['client_id', 'external_id']);
            });

            if ($name === 'orders') {
                DB::table('orders')->update(['sync_acked_version' => 1]);
            }
        }
    }

    public function down(): void
    {
        foreach (['products', 'orders'] as $name) {
            if (! Schema::hasColumn($name, 'external_id')) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropUnique($name.'_client_id_external_id_unique');
                $table->dropColumn(['external_id', 'sync_version', 'sync_acked_version']);
            });
        }
    }
};
