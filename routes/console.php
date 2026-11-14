<?php

use App\Templates\TemplateManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---- Storefront templates -------------------------------------------------

Artisan::command('template:list', function (TemplateManager $templates) {
    $this->table(['Id', 'Name', 'Installed'], $templates->all()->map(fn ($t) => [
        $t->id(), $t->name(), $t->isInstalled() ? 'yes' : 'no',
    ])->values()->all());
})->purpose('List storefront templates');

Artisan::command('template:install {template}', function (TemplateManager $templates, string $template) {
    $t = $templates->get($template);
    $t->install();
    $this->info("{$t->name()} demo content installed (settings, menus, catalogue).");
})->purpose('Seed a template\'s demo content (idempotent)');

