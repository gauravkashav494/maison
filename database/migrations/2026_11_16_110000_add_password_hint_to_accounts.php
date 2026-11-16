<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A readable copy of each account's password, shown to a SuperAdmin on /admin and to a
 * Client Admin on /client. Filled by App\Models\Concerns\StoresPasswordHint whenever a
 * password is set; existing accounts stay empty until their password is next changed.
 * All three tables live in the platform database only.
 */
return new class extends Migration
{
    private const TABLES = ['users', 'client_users', 'customers'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'password_hint')) {
                Schema::table($name, fn (Blueprint $table) => $table->string('password_hint')->nullable()->after('password'));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasColumn($name, 'password_hint')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('password_hint'));
            }
        }
    }
};
