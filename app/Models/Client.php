<?php

namespace App\Models;

use App\Templates\Template;
use App\Templates\TemplateManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A client is the tenant: the business renting the platform.
 *
 * It is assigned one store (storefront/template) and owns its own database, so two clients
 * running the same store share nothing — not products, not orders, not customers. Every
 * content row also carries the client id, which keeps a single-database install isolated.
 *
 * Limits (products, images, storage) come from the plan and can be overridden per client.
 */
class Client extends Model
{
    use \App\Models\Concerns\UsesPlatformConnection;

    use Concerns\LogsPlatformActivity;

    public const COOKIE = 'client';

    /** Session key mirroring the cookie, so an entry link survives a browser that drops it. */
    public const SESSION_KEY = 'storefront.client';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'is_theme' => 'boolean', 'modules' => 'array'];

    /** Global scope that keeps theme previews out of every client list, count and total. */
    public const HIDE_THEMES = 'hide_themes';

    protected static function booted(): void
    {
        static::addGlobalScope(self::HIDE_THEMES, fn (Builder $query) => $query->where($query->qualifyColumn('is_theme'), false));
        static::created(fn (Client $client) => ClientRole::createDefaultsFor($client));
        static::saved(fn () => Cache::forget('clients.hosts'));
        static::deleted(fn () => Cache::forget('clients.hosts'));
    }

    // ---- Relationships -----------------------------------------------------

    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Storefront::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Client Admin and Admin Staff accounts for this client. */
    public function users(): HasMany
    {
        return $this->hasMany(ClientUser::class)->orderBy('name');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ClientRole::class)->orderBy('name');
    }

    /** Is this module switched on for the client? Null means nobody has narrowed the list yet. */
    public function hasModule(string $module): bool
    {
        return $this->modules === null || in_array($module, $this->modules, true);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class)->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ---- Store / template --------------------------------------------------

    /** Template id of the store this client runs. */
    public function templateId(): ?string
    {
        return $this->storefront?->template;
    }

    public function templateObject(): ?Template
    {
        $manager = app(TemplateManager::class);
        $id = $this->templateId();

        return $id && $manager->has($id) ? $manager->get($id) : null;
    }

    public function supportsServices(): bool
    {
        return (bool) $this->templateObject()?->supportsServices();
    }

    // ---- Database ----------------------------------------------------------

    /** Database (or PostgreSQL schema) holding this client's content. */
    public function databaseName(): string
    {
        return $this->database ?: config('clients.prefix', 'client_').$this->key();
    }

    /** Slug in a form that is safe inside an identifier or a connection name. */
    public function key(): string
    {
        return str_replace('-', '_', (string) $this->slug);
    }

    // ---- Limits ------------------------------------------------------------

    /** Maximum number of products; null = unlimited. */
    public function productLimit(): ?int
    {
        return $this->unlimitedAsNull($this->limit('product_limit'));
    }

    /**
     * Maximum number of product images: the client's own figure, else its plan's, else the
     * platform default of 50. Zero means unlimited, which is how a paid plan lifts the cap.
     */
    public function imageLimit(): ?int
    {
        $limit = $this->limit('image_limit') ?? (int) config('clients.default_image_limit', 50);

        return $this->unlimitedAsNull($limit);
    }

    /** Maximum media storage in megabytes; null = unlimited. */
    public function storageLimitMb(): ?int
    {
        return $this->unlimitedAsNull($this->limit('storage_limit_mb'));
    }

    private function unlimitedAsNull(?int $limit): ?int
    {
        return $limit === 0 ? null : $limit;
    }

    private function limit(string $column): ?int
    {
        $own = $this->{$column};
        if ($own !== null) {
            return (int) $own;
        }
        $plan = $this->plan?->{$column};

        return $plan === null ? null : (int) $plan;
    }

    // ---- Public addressing -------------------------------------------------

    /** Host (no port) the platform itself answers on. */
    public static function baseDomain(): string
    {
        return Storefront::baseDomain();
    }

    /** Host → client id for every active client (custom domain and <slug>.<base> forms). */
    public static function hostMap(): array
    {
        return Cache::remember('clients.hosts', 60, function () {
            $base = static::baseDomain();
            $map = [];
            foreach (static::withThemes()->active()->get() as $client) {
                $map[strtolower($client->slug.'.'.$base)] = $client->id;
                if ($client->domain) {
                    $map[strtolower($client->domain)] = $client->id;
                }
            }

            return $map;
        });
    }

    /** Client served on this host, or null for the main domain / unknown hosts. */
    public static function idForHost(string $host): ?int
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host));

        return static::hostMap()[$host] ?? null;
    }

    /** Active client with this slug, if any. */
    public static function idForSlug(?string $slug): ?int
    {
        if (! $slug) {
            return null;
        }

        return static::active()->where('slug', strtolower($slug))->value('id');
    }

    /**
     * Public address of this client's storefront: its custom domain, else <slug>.<base domain>.
     * A client is always reached on its own host, never through a path on the main domain.
     */
    public function publicUrl(): string
    {
        if ($this->domain) {
            return 'https://'.$this->domain;
        }
        // Scheme and port follow the current request (dev servers run on :8000), else APP_URL.
        $request = app()->bound('request') ? request() : null;
        $app = parse_url(config('app.url'));
        $scheme = $request?->getScheme() ?? ($app['scheme'] ?? 'http');
        $portNo = $request?->getPort() ?? ($app['port'] ?? null);
        $port = $portNo && ! in_array((int) $portNo, [80, 443], true) ? ':'.$portNo : '';

        return $scheme.'://'.$this->slug.'.'.static::baseDomain().$port;
    }

    /** Sign-in page of the client panel, on the same host as the store. */
    public function loginUrl(): string
    {
        return $this->publicUrl().'/client/login';
    }

    // ---- Theme previews ----------------------------------------------------

    /** Clients including the theme previews, for code that must reach them. */
    public static function withThemes(): Builder
    {
        return static::withoutGlobalScope(self::HIDE_THEMES);
    }

    /** theme-<template>: the address word is config('templates.theme_prefix'). */
    public static function themeSlug(string $templateId): string
    {
        return config('templates.theme_prefix', 'theme-').$templateId;
    }

    /** The theme preview of a template, if it has been built. */
    public static function themeFor(?string $templateId): ?self
    {
        return $templateId
            ? static::withThemes()->where('is_theme', true)->where('slug', static::themeSlug($templateId))->first()
            : null;
    }

    /** Suggests a unique slug for a new client. */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'client';
        // The theme- prefix is kept for theme previews.
        if (str_starts_with($base, config('templates.theme_prefix', 'theme-'))) {
            $base = 'store-'.$base;
        }
        $slug = $base;
        for ($i = 2; static::withThemes()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
