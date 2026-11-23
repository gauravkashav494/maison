<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The activity log now covers everything a client's staff do, not only account records, and
 * every line carries a ready-to-read sentence ("changed the price of product "Silk Saree" from
 * ₹4,500.00 to ₹3,999.00"). Older lines keep working: they have no description and are shown
 * from their action, type and record name as before.
 *
 *   subject_id   the record, so its own history can be listed on its page
 *   area         Products, Orders, Settings, Sign-in … for filtering
 *   description  the sentence, without the actor (shown next to it)
 *   changes      [{field, label, old, new}] — values only for safe, short fields
 *   ip           where the change came from
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_activities', function (Blueprint $table) {
            $table->string('action', 40)->change();
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->string('area', 60)->nullable()->after('subject');
            $table->text('description')->nullable()->after('area');
            $table->json('changes')->nullable()->after('fields');
            $table->string('ip', 45)->nullable()->after('changes');
            $table->index(['client_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('platform_activities', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'created_at']);
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropColumn(['subject_id', 'area', 'description', 'changes', 'ip']);
        });
    }
};
