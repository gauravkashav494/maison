<?php

use App\Templates\TemplateManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---- Storefront templates -------------------------------------------------

Artisan::command('template:list', function (TemplateManager $templates) {
    $active = $templates->activeId();
    $this->table(['Id', 'Name', 'Installed', 'Active'], $templates->all()->map(fn ($t) => [
        $t->id(), $t->name(), $t->isInstalled() ? 'yes' : 'no', $t->id() === $active ? '●' : '',
    ])->values()->all());
})->purpose('List storefront templates');

Artisan::command('template:install {template}', function (TemplateManager $templates, string $template) {
    $t = $templates->get($template);
    $t->install();
    $this->info("{$t->name()} demo content installed (settings, menus, catalogue).");
})->purpose('Seed a template\'s demo content (idempotent)');

Artisan::command('template:activate {template}', function (TemplateManager $templates, string $template) {
    $t = $templates->get($template);
    if (! $t->isInstalled()) {
        $this->error("{$t->name()} has no content yet — run template:install {$template} first.");

        return 1;
    }
    $templates->activate($template);
    $this->info("{$t->name()} is now the active storefront template.");
})->purpose('Make a template live for visitors');
