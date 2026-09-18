<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Templates\TemplateManager;
use Illuminate\Database\Seeder;

/**
 * Stores the Indian Grocery template's default header/homepage content so it can be
 * edited in Appearance → Grocery. Never overwrites groups that already exist.
 */
class GrocerySettingsSeeder extends Seeder
{
    public function run(): void
    {
        $template = app(TemplateManager::class)->get('grocery');

        foreach ($template->settingGroups() as $alias => $group) {
            if (Setting::get($group) === null) {
                Setting::set($group, $template->defaults()[$alias] ?? []);
            }
        }
    }
}
