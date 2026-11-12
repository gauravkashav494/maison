<?php

namespace App\Clients;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Storefront;
use App\Templates\TemplateManager;

/**
 * Builds a template's theme preview: an internal client named theme-<template>, with its own
 * database seeded with the template's sample content. Public at theme-<template>.<base domain>,
 * hidden from client lists. Running it again only adds what is missing; the content is kept.
 */
class ThemeBuilder
{
    public function __construct(
        private readonly ClientProvisioner $provisioner,
        private readonly ClientDatabase $databases,
    ) {}

    /** @return array{ok: bool, message: string, client?: Client} */
    public function build(string $templateId): array
    {
        $manager = app(TemplateManager::class);
        if (! $manager->has($templateId)) {
            return ['ok' => false, 'message' => "There is no {$templateId} template in the code."];
        }
        if (! $this->databases->isolated()) {
            return ['ok' => false, 'message' => 'Theme previews need their own database: set CLIENT_ISOLATION=database (or schema).'];
        }

        $store = Storefront::where('template', $templateId)->orderByDesc('is_active')->orderBy('id')->first();
        if (! $store) {
            return ['ok' => false, 'message' => 'No store uses the '.$manager->get($templateId)->name().' template yet. Add one under Platform → Stores first.'];
        }

        $client = Client::themeFor($templateId) ?? new Client(['slug' => Client::themeSlug($templateId)]);
        $client->fill([
            'name' => $manager->get($templateId)->name().' theme',
            'storefront_id' => $store->id,
            'plan_id' => $client->plan_id ?? Plan::default()?->id,
            'is_theme' => true,
            'is_active' => true,
            'modules' => null,
        ])->save();

        $result = $this->provisioner->provision($client, withSamples: true);

        return $result + ['client' => $client];
    }
}
