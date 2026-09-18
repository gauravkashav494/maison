<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@maisonelan.com'],
            ['name' => 'Administrator', 'password' => Hash::make('password'), 'is_admin' => true],
        );

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
        ]);
    }
}
