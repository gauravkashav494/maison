<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which account wrote each activity line, so platform staff can be shown only their own
 * (a Super Admin sees everyone's). `actor` stays as the readable name. Lines written before
 * this migration have no id and are therefore shown to Super Admins only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_activities', function (Blueprint $table) {
            $table->string('actor_model', 60)->nullable()->after('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable()->after('actor_model');
            $table->index(['actor_model', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('platform_activities', function (Blueprint $table) {
            $table->dropIndex(['actor_model', 'actor_id']);
            $table->dropColumn(['actor_model', 'actor_id']);
        });
    }
};
