<?php

namespace App\Templates\Fashion;

use App\Filament\Pages\HomepageSettings;
use App\Models\Menu;
use App\Templates\Template;

/**
 * The original Maison Élan editorial fashion storefront. Its views live in the
 * base resources/views folder, which is also the fallback for every other template.
 */
class FashionTemplate extends Template
{
    public function id(): string
    {
        return 'fashion';
    }

    public function name(): string
    {
        return 'Fashion';
    }

    public function description(): string
    {
        return 'Editorial luxury storefront for clothing, fragrance, watches, bags and accessories — serif typography, ivory palette, campaign-led homepage, mega-menu navigation.';
    }

    public function thumbnail(): ?string
    {
        return '/templates/fashion/thumbnail.jpg';
    }

    public function pwa(): array
    {
        return ['name' => setting('site.name', config('app.name')), 'theme_color' => '#f6f3ee', 'background_color' => '#f6f3ee'];
    }

    public function viewPath(): ?string
    {
        return null;
    }

    public function assets(): array
    {
        return ['resources/css/app.css', 'resources/js/app.js'];
    }

    public function menuLocations(): array
    {
        $out = [];
        foreach (Menu::LOCATIONS as $key => $label) {
            $out[$key] = [$key, $label];
        }

        return $out;
    }

    public function settingGroups(): array
    {
        return ['site' => 'site', 'home' => 'home'];
    }

    public function adminPages(): array
    {
        return [HomepageSettings::class];
    }
}
