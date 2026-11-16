<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lets a subscriber leave the newsletter from a link, in a client database. The
 * shared-database copy is database/migrations/2026_11_14_120000_add_unsubscribe_to_subscribers.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscribers') || Schema::hasColumn('subscribers', 'unsubscribe_token')) {
            return;
        }

        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('unsubscribe_token', 64)->nullable()->unique()->after('source');
            // Set when the subscriber leaves by themselves; a row an admin removed is soft-deleted instead.
            $table->timestamp('unsubscribed_at')->nullable()->after('unsubscribe_token');
        });

        // Existing subscribers get a token too, so every row has a working link.
        DB::table('subscribers')->whereNull('unsubscribe_token')->orderBy('id')->select('id')
            ->each(fn ($row) => DB::table('subscribers')->where('id', $row->id)->update(['unsubscribe_token' => Str::random(40)]));
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscribers', 'unsubscribe_token')) {
            Schema::table('subscribers', function (Blueprint $table) {
                $table->dropUnique(['unsubscribe_token']);
                $table->dropColumn(['unsubscribe_token', 'unsubscribed_at']);
            });
        }
    }
};
