<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientRole;
use App\Models\ClientUser;
use Illuminate\Database\Seeder;

/**
 * Sign-in accounts for every demo store, so its client panel (/client on the store's own
 * subdomain) can be opened locally. For each store (theme previews excepted):
 *
 *   admin@<subdomain>.test   Client Admin: everything, staff accounts included
 *   staff@<subdomain>.test   Admin Staff with the ready-made "Manager" role
 *
 * Both use the password "password". The ready-made roles are added first when a store has
 * none. Safe to run again: an existing account is left as it is (its password included).
 *
 *   php artisan db:seed --class=DemoStaffSeeder
 */
class DemoStaffSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        $clients = Client::withoutGlobalScopes()->where('is_theme', false)->orderBy('id')->get();

        if ($clients->isEmpty()) {
            $this->command?->warn('No clients yet: create the demo stores first, then run this seeder.');

            return;
        }

        $rows = [];

        foreach ($clients as $client) {
            ClientRole::createDefaultsFor($client);
            $manager = ClientRole::where('client_id', $client->id)->where('name', 'Manager')->first();

            $admin = $this->account($client, 'admin', $client->name.' Admin', ClientUser::ROLE_CLIENT_ADMIN, null);
            $staff = $this->account($client, 'staff', $client->name.' Manager', ClientUser::ROLE_ADMIN_STAFF, $manager?->id);

            $rows[] = [$client->name, $client->loginUrl(), $admin->email, $staff->email];
        }

        $this->command?->table(['Store', 'Sign in at', 'Client Admin', 'Staff (Manager)'], $rows);
        $this->command?->info('Password for every account: '.self::PASSWORD);
    }

    private function account(Client $client, string $who, string $name, string $role, ?int $roleId): ClientUser
    {
        return ClientUser::withTrashed()->firstOrCreate(
            ['client_id' => $client->id, 'email' => $who.'@'.$client->subdomain.'.test'],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'role' => $role,
                'client_role_id' => $roleId,
                'is_active' => true,
            ],
        );
    }
}
