<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The platform's Super Admin. is_super_admin is the flag that counts (User keeps role and
        // is_admin in step with it). An account still on the old address is moved to the new one
        // rather than duplicated; an existing account keeps its password.
        $admin = User::query()->where('email', 'admin@ecomvision.in')->first()
            ?? User::query()->where('email', 'admin@maisonelan.com')->first();

        if ($admin) {
            $admin->update(['email' => 'admin@ecomvision.in', 'is_super_admin' => true, 'is_active' => true]);
        } else {
            User::query()->create([
                'name' => 'Administrator',
                'email' => 'admin@ecomvision.in',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'is_active' => true,
            ]);
        }

        $this->call([
            CatalogSeeder::class,
            MenuSeeder::class,
            ContentSeeder::class,
            SettingsSeeder::class,
            CommerceSeeder::class,
            PagesSeeder::class,
            // Indian Grocery template demo content (own categories/products/menus/settings)
            GrocerySettingsSeeder::class,
            GroceryMenuSeeder::class,
            GroceryCatalogSeeder::class,
            GroceryContentSeeder::class,
            // Heritage Grocery template demo content
            HeritageSettingsSeeder::class,
            HeritageMenuSeeder::class,
            HeritageCatalogSeeder::class,
            HeritageContentSeeder::class,
            // Sign-in accounts for the demo stores' client panels (skipped when there are no clients yet)
            DemoStaffSeeder::class,
        ]);
    }
}
