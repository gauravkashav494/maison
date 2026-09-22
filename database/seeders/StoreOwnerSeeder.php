<?php

namespace Database\Seeders;

use App\Models\Storefront;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One demo Store Owner per storefront (password: "password"). Idempotent: existing
 * accounts with the same email are left untouched.
 */
class StoreOwnerSeeder extends Seeder
{
    public function run(): void
    {
        Storefront::syncWithTemplates();

        foreach (Storefront::all() as $store) {
            $email = Str::slug($store->template).'.owner@maisonelan.com';
            if (User::where('email', $email)->exists()) {
                continue;
            }
            User::create([
                'name' => Str::of($store->name)->replace(' Store', '')->append(' Owner')->toString(),
                'email' => $email,
                'password' => 'password',
                'is_admin' => true,
                'role' => User::ROLE_STORE_OWNER,
                'storefront_id' => $store->id,
                'is_active' => true,
            ]);
        }
    }
}
