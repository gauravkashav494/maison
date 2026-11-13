<?php

namespace App\Templates;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Arr;

/**
 * A storefront template: a self-contained set of Blade views, assets, menu
 * locations and settings groups rendered on top of the shared commerce backend.
 */
abstract class Template
{
    /** Stable identifier stored in settings, menus and catalogue visibility. */
    abstract public function id(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    /** Public URL of a preview image shown on the Templates admin page. */
    public function thumbnail(): ?string
    {
        return null;
    }

    /**
     * Name and colours used by the installable web app (manifest, home-screen label, splash screen).
     * Icons live in public/templates/{id}/ (icon-192.png, icon-512.png, icon-maskable-512.png, apple-touch-icon.png).
     */
    public function pwa(): array
    {
        return ['name' => setting('site.name', config('app.name')), 'theme_color' => '#111111', 'background_color' => '#ffffff'];
    }

    /**
     * Folder (relative to resources/views) that is searched before the base views.
     * Return null for the template that lives in the base views folder.
     */
    abstract public function viewPath(): ?string;

    /** Vite entry points loaded by this template's layout. */
    abstract public function assets(): array;

    /**
     * Menu locations owned by this template: alias => [location key, label].
     * The alias is how the template's views refer to the menu ($menus['header']);
     * the location key is what is stored in the menus table.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    abstract public function menuLocations(): array;

    /**
     * Settings groups owned by this template: alias => settings key.
     *
     * @return array<string, string>
     */
    abstract public function settingGroups(): array;

    /** Default values for the template's settings groups, keyed by alias. */
    public function defaults(): array
    {
        return [];
    }

    /**
     * The store team's sign-in page (/client/login) for stores on this template. A client can
     * override any of it in Site settings → Sign-in page. ":name" becomes the client's name.
     *
     * @return array{style: string, image: ?string, kicker: string, heading: string, subtitle: string, quote: string, quote_sub: string, help: string}
     */
    public function teamLogin(): array
    {
        return static::defaultTeamLogin();
    }

    /** The plain sign-in page, for a template that sets none of its own. */
    public static function defaultTeamLogin(): array
    {
        return [
            'style' => 'classic',
            'image' => null,
            'kicker' => 'Team sign-in',
            'heading' => 'Welcome back',
            'subtitle' => 'Sign in to manage :name.',
            'quote' => 'Your store, all in one place.',
            'quote_sub' => 'Orders, products, customers and content — managed from one calm dashboard.',
            'help' => 'Trouble signing in? Ask your store admin to reset your access.',
        ];
    }

    /** Filament page classes that configure this template. */
    public function adminPages(): array
    {
        return [];
    }

    /** Whether the template's demo content (settings, menus, catalogue) has been seeded. */
    public function isInstalled(): bool
    {
        return true;
    }

    /** Seed demo content for the template. Must be idempotent. */
    public function install(): void {}

    /**
     * The page layouts (Page::TEMPLATES keys) this template has its own view for. A layout it
     * lacks would fall back to the base (Fashion) design inside this store, so the page form
     * only offers these. Read from the template's view folder, so a new layout view is offered
     * as soon as it exists.
     *
     * @return array<int, string>
     */
    public function pageLayouts(): array
    {
        $folder = resource_path('views/'.trim(($this->viewPath() ?? ''), '/').'/pages/templates');

        return array_values(array_filter(
            array_keys(Page::TEMPLATES),
            fn (string $layout) => is_file($folder.'/'.$layout.'.blade.php'),
        ));
    }

    /** Product field groups the product form can show; see productFields(). */
    public const PRODUCT_FIELDS_GROCERY = 'grocery';          // veg mark, shelf life, origin, ingredients, storage

    public const PRODUCT_FIELDS_NUTRITION = 'nutrition';      // dietary tags, benefits, how to use, nutrition table

    public const PRODUCT_FIELDS_SPECIFICATIONS = 'specifications'; // spec table, applications, installation notes

    public const PRODUCT_FIELDS_ORDER_LIMIT = 'order_limit';  // maximum quantity per order

    /**
     * The extra product field groups this template's storefront actually shows. The product form
     * offers only these, so a store is never asked for details its pages will not display.
     *
     * @return array<int,string>
     */
    public function productFields(): array
    {
        return [];
    }

    /** Whether this template renders service pages (booking, service areas) instead of the catalogue. */
    public function supportsServices(): bool
    {
        return false;
    }

    /** Called once per request when this template renders it (register composers, etc.). */
    public function boot(): void {}

    /** Extra variables shared with the template's layout and partials. */
    public function viewData(): array
    {
        return [];
    }

    // ---- Helpers shared by every template --------------------------------

    /** Menu location keys (without aliases). */
    public function locationKeys(): array
    {
        return array_map(fn ($l) => $l[0], $this->menuLocations());
    }

    /** Location key => human label, for admin selects. */
    public function locationLabels(): array
    {
        $out = [];
        foreach ($this->menuLocations() as [$key, $label]) {
            $out[$key] = $label;
        }

        return $out;
    }

    /**
     * Read a template setting with config defaults: "home.hero_heading".
     * The first segment is the settings-group alias.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        [$alias, $path] = array_pad(explode('.', $key, 2), 2, null);
        $group = $this->settingGroups()[$alias] ?? $alias;
        $stored = Setting::get($group, []);
        $defaults = $this->defaults()[$alias] ?? [];

        if ($path === null) {
            return array_replace($defaults, is_array($stored) ? $stored : []);
        }

        $value = Arr::get($stored, $path);
        if ($value === null || $value === '' || $value === []) {
            $value = Arr::get($defaults, $path);
        }

        return $value ?? $default;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'description' => $this->description(),
            'thumbnail' => $this->thumbnail(),
            'installed' => $this->isInstalled(),
        ];
    }
}
