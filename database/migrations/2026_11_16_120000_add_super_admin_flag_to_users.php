<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform accounts are either a Super Admin (`is_super_admin`, everything) or platform staff,
 * who reach only the /admin modules listed in `admin_modules` (see App\Admin\PlatformModules).
 * Every existing account is a Super Admin, so they keep the access they have today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_super_admin')) {
                $table->boolean('is_super_admin')->default(false)->after('role');
            }
            if (! Schema::hasColumn('users', 'admin_modules')) {
                $table->json('admin_modules')->nullable()->after('is_super_admin');
            }
        });

        DB::table('users')->where('role', 'super_admin')->orWhere('is_admin', true)->update(['is_super_admin' => true, 'role' => 'super_admin']);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_super_admin', 'admin_modules']));
    }
};
