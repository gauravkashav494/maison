<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a client's agent finds the shop's billing software (docs/LOCAL-API.md): its API address
 * and key, set in the panel and read by the agent every cycle, so the installer asks only for
 * the setup code. The key is stored encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_agents', function (Blueprint $table) {
            $table->string('local_api_url', 500)->nullable()->after('client_id');
            $table->text('local_api_key')->nullable()->after('local_api_url');
        });
    }

    public function down(): void
    {
        Schema::table('sync_agents', function (Blueprint $table) {
            $table->dropColumn(['local_api_url', 'local_api_key']);
        });
    }
};
