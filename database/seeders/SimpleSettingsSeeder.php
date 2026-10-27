<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Templates\TemplateManager;
use Illuminate\Database\Seeder;

/**
 * Stores the Simple List template's defaults so they can be edited in the admin.
 * Never overwrites a group that already exists.
 */
class SimpleSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $template = app(TemplateManager::class)->get('simple');

        foreach ($template->settingGroups() as $alias => $group) {
            if (Setting::get($group) === null) {
                Setting::set($group, $template->defaults()[$alias] ?? []);
            }
        }
    }
}
