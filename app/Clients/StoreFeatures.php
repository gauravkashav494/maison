<?php

namespace App\Clients;

use App\Models\Client;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route as Router;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Which optional modules the client being served has switched on, and what that means for the
 * public store and the admin forms. A module switched off on the client is off everywhere:
 * its admin screens close (ClientAccess), its fields in other forms are hidden, and on the
 * storefront its records, pages, forms and links go (config/client_modules.php → 'storefront').
 *
 * No client (the platform side with no client chosen) means nothing is narrowed.
 */
class StoreFeatures
{
    /** Name of the global scope that empties a switched-off module's records on the storefront. */
    public const SCOPE = 'module_off';

    /** @var array<string>|null CMS page slugs that belong to a switched-off module */
    private ?array $blockedSlugs = null;

    private ?int $slugsFor = null;

    public function client(): ?Client
    {
        return app(ClientContext::class)->client();
    }

    public function on(string $module): bool
    {
        $client = $this->client();

        return ! $client || $client->hasModule($module);
    }

    /** Shorthand for forms and tables: is this module switched on for the client in context? */
    public static function enabled(string $module): bool
    {
        return app(self::class)->on($module);
    }

    /** @return array<string> keys of the client's modules that are switched off */
    public function off(): array
    {
        $client = $this->client();
        if (! $client) {
            return [];
        }

        return array_values(array_filter(array_keys(ClientModules::all()), fn (string $key) => ! $client->hasModule($key)));
    }

    /** True once ResolveTemplate has set up a storefront request (the admin is never narrowed this way). */
    private bool $storefront = false;

    /**
     * Storefront: a switched-off module's records read as empty for this request. The scope is
     * registered on every hideable model and decides per query, so a model booted by an earlier
     * request (or test) never keeps a stale answer.
     */
    public function hideStorefrontData(): void
    {
        $this->storefront = true;

        foreach (ClientModules::all() as $key => $module) {
            if (($module['storefront']['hide'] ?? false) && $module['model']) {
                $module['model']::addGlobalScope(self::SCOPE, function (Builder $query) use ($key) {
                    $features = app(self::class);
                    if ($features->storefront && ! $features->on($key)) {
                        $query->whereRaw('1 = 0');
                    }
                });
            }
        }
    }

    /** Would this route answer 404 because its module is switched off? */
    public function blocksRoute(?Route $route): bool
    {
        if (! $route) {
            return false;
        }

        foreach ($this->off() as $key) {
            if ($route->named(ClientModules::get($key)['storefront']['routes'] ?? [])) {
                return true;
            }
        }

        return $route->getName() === 'pages.show'
            && in_array((string) $route->parameter('slug'), $this->blockedPageSlugs(), true);
    }

    /** Storefront: refuse a page or form of a switched-off module. */
    public function guard(?Route $route): void
    {
        if ($this->blocksRoute($route)) {
            throw new HttpException(404);
        }
    }

    /** False for an internal link that would land on a switched-off module. External links pass. */
    public function allowsUrl(?string $url): bool
    {
        if (blank($url) || $url === '#' || ! $this->off()) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host && strcasecmp($host, Request::getHost()) !== 0) {
            return true;
        }

        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        try {
            $route = Router::getRoutes()->match(\Illuminate\Http\Request::create($path));
        } catch (\Throwable) {
            return true;
        }

        return ! $this->blocksRoute($route->bind(\Illuminate\Http\Request::create($path)));
    }

    /**
     * Menu items (with nested children) minus the ones that point at a switched-off module.
     *
     * @param  SupportCollection<int, MenuItem>  $items
     */
    public function filterMenu(SupportCollection $items): SupportCollection
    {
        if (! $this->off()) {
            return $items;
        }

        return $items
            ->filter(fn (MenuItem $item) => $this->allowsUrl($item->url))
            ->map(function (MenuItem $item) {
                $item = clone $item;
                $item->setRelation('children', $this->filterMenu($item->children ?? collect()));

                return $item;
            })
            ->values();
    }

    /** @return array<string> */
    private function blockedPageSlugs(): array
    {
        $client = $this->client();
        if ($this->blockedSlugs !== null && $this->slugsFor === $client?->id) {
            return $this->blockedSlugs;
        }

        $templates = [];
        foreach ($this->off() as $key) {
            array_push($templates, ...(ClientModules::get($key)['storefront']['pages'] ?? []));
        }

        $this->slugsFor = $client?->id;

        return $this->blockedSlugs = $templates
            ? Page::query()->whereIn('template', $templates)->pluck('slug')->unique()->values()->all()
            : [];
    }

    /** Mainly for tests: forget what was looked up for the previous request. */
    public function flush(): void
    {
        $this->blockedSlugs = null;
        $this->slugsFor = null;
    }
}
