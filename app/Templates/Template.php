<?php

namespace App\Templates;

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
