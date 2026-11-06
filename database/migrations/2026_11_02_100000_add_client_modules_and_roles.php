<?php

use App\Clients\ClientModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What each client may use, and what each staff member may do.
 *
 *   clients.modules            modules switched on for the client; null = all its store supports
 *   client_roles               roles a Client Admin builds from permissions, one set per client
 *   client_users.client_role_id  the role of an Admin Staff account
 *   platform_activities        who changed which account or platform record
 *
 * Every existing client gets the ready-made roles, and existing Admin Staff become Managers,
 * which keeps what they could already do. All of this lives in the main database, whichever
 * CLIENT_ISOLATION mode is used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('modules')->nullable()->after('storage_limit_mb');
        });

        Schema::create('client_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'name']);
        });

        Schema::table('client_users', function (Blueprint $table) {
            $table->foreignId('client_role_id')->nullable()->after('role')->constrained('client_roles')->nullOnDelete();
        });

        Schema::create('platform_activities', function (Blueprint $table) {
            $table->id();
            $table->string('actor');
            $table->string('actor_type', 40);
            $table->string('action', 20);
            $table->string('subject_type', 60);
            $table->string('subject');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->json('fields')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        $now = now();
        foreach (DB::table('clients')->pluck('id') as $clientId) {
            foreach (ClientModules::defaultRoles() as $name => $role) {
                DB::table('client_roles')->insert([
                    'client_id' => $clientId,
                    'name' => $name,
                    'description' => $role['description'],
                    'permissions' => json_encode($role['permissions']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $manager = DB::table('client_roles')->where('client_id', $clientId)->where('name', 'Manager')->value('id');
            DB::table('client_users')->where('client_id', $clientId)->where('role', 'admin_staff')->update(['client_role_id' => $manager]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_activities');
        Schema::table('client_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_role_id');
        });
        Schema::dropIfExists('client_roles');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('modules');
        });
    }
};
