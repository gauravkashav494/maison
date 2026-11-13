<?php

namespace App\Console\Commands;

use App\Clients\ThemeBuilder;
use App\Templates\TemplateManager;
use Illuminate\Console\Command;

/**
 * Builds the public theme previews (theme-<template>.<base domain>) with sample content.
 *
 *   php artisan themes:build           # every template that a store uses
 *   php artisan themes:build fashion   # one template
 */
class ThemesBuild extends Command
{
    protected $signature = 'themes:build {template? : Template id, e.g. fashion}';

    protected $description = 'Create or update the public theme preview of each template';

    public function handle(ThemeBuilder $builder, TemplateManager $templates): int
    {
        $ids = $this->argument('template') ? [$this->argument('template')] : $templates->all()->keys()->all();
        $failed = false;

        foreach ($ids as $id) {
            $result = $builder->build($id);
            if ($result['ok']) {
                $this->components->info($id.': '.$result['client']->publicUrl());
            } else {
                $this->components->warn($id.': '.$result['message']);
                $failed = $failed || $this->argument('template');
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
