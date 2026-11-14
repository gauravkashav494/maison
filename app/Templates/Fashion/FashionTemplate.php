<?php

namespace App\Templates\Fashion;

use App\Filament\Pages\HomepageSettings;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Support\Media;
use App\Templates\Template;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PagesSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\StoreLocationsSeeder;
use Illuminate\Support\Facades\Cache;

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

    public function usesSiteBranding(): bool
    {
        return true;
    }

    public function adminPages(): array
    {
        return [HomepageSettings::class];
    }

    public function isInstalled(): bool
    {
        return Category::where('template', $this->id())->exists()
            || Product::where('template', $this->id())->exists();
    }

    /** Demo content for this store: catalogue, navigation, pages, journal and settings. */
    public function install(): void
    {
        app(CatalogSeeder::class)->run();
        app(MenuSeeder::class)->run();
        app(ContentSeeder::class)->run();
        app(PagesSeeder::class)->run();
        app(SettingsSeeder::class)->run();
        app(StoreLocationsSeeder::class)->run('fashion');
        Cache::flush();
    }

    public function teamLogin(): array
    {
        return array_replace(parent::teamLogin(), [
            'image' => Media::unsplash('1571513722275-4b41940f54b8', 1600, '&h=1200'),
            'kicker' => 'Atelier',
            'quote' => 'Dress the season.',
            'quote_sub' => 'Collections, campaigns, orders and clients — your boutique, behind the scenes.',
        ]);
    }
}
