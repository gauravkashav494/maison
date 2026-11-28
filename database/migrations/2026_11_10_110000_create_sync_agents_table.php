<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What EcomVision holds for a client's installed agent: its version and what it last reported. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('agent_version', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('last_report')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_agents');
    }
};
