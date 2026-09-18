<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Templates\TemplateManager;
use Illuminate\Database\Seeder;

/**
 * Stores the Indian Heritage template's default header/homepage content so it can be
 * edited in Appearance → Heritage. Never overwrites groups that already exist.
 */
class HeritageSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $template = app(TemplateManager::class)->get('heritage');

        foreach ($template->settingGroups() as $alias => $group) {
            if (Setting::get($group) === null) {
                Setting::set($group, $template->defaults()[$alias] ?? []);
            }
        }
    }
}
