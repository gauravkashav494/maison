<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional custom domain per store; without one the store answers on <slug>.<base domain>. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefronts', function (Blueprint $table) {
            $table->string('domain', 190)->nullable()->unique()->after('template');
        });
    }

    public function down(): void
    {
        Schema::table('storefronts', fn (Blueprint $table) => $table->dropColumn('domain'));
    }
};
