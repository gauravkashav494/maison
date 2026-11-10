<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the old Store Owner accounts from `users`, which holds SuperAdmins only. Client
 * owners and staff live in `client_users` and customers in `customers`.
 *
 *   php artisan users:remove-store-owners           # asks before deleting
 *   php artisan users:remove-store-owners --force   # no confirmation
 *
 * Also drops `users.storefront_id`, which only those accounts used. Safe to re-run.
 */
class UsersRemoveStoreOwners extends Command
{
    protected $signature = 'users:remove-store-owners {--force : Delete without asking}';

    protected $description = 'Delete the old Store Owner accounts from the users table';

    public function handle(): int
    {
        $owners = DB::table('users')->where('role', 'store_owner')->get(['id', 'email']);

        if ($owners->isEmpty()) {
            $this->components->info('No Store Owner accounts found.');
        } else {
            $this->table(['ID', 'Email'], $owners->map(fn ($u) => [$u->id, $u->email]));

            if (! $this->option('force') && ! $this->confirm("Delete these {$owners->count()} accounts?")) {
                $this->components->warn('Nothing deleted.');

                return self::SUCCESS;
            }

            DB::table('users')->whereIn('id', $owners->pluck('id'))->delete();
            $this->components->info("Deleted {$owners->count()} Store Owner accounts.");
        }

        if (Schema::hasColumn('users', 'storefront_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('storefront_id'));
            $this->components->info('Dropped users.storefront_id.');
        }

        return self::SUCCESS;
    }
}
