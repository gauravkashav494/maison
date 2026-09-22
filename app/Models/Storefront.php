<?php

namespace App\Models;

use App\Templates\Template;
use App\Templates\TemplateManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A store = one website managed by its owners. Each storefront runs one template, and
 * the template id is what every content row is scoped by (the `template` column).
 * Not to be confused with Store, which is the physical store-locator entry.
 */
class Storefront extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('storefronts.hosts'));
        static::deleted(fn () => Cache::forget('storefronts.hosts'));
    }

    /** Host (no port) that the platform's main domain answers on. */
    public static function baseDomain(): string
    {
        return strtolower(config('templates.base_domain') ?: (parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'));
    }

    /** Host → template id for every active store (custom domain and <slug>.<base> forms). */
    public static function hostMap(): array
    {
        return Cache::remember('storefronts.hosts', 60, function () {
            $base = static::baseDomain();
            $map = [];
            foreach (static::active()->get() as $store) {
                $map[strtolower($store->slug.'.'.$base)] = $store->template;
                if ($store->domain) {
                    $map[strtolower($store->domain)] = $store->template;
                }
            }

            return $map;
        });
    }

    /** Template served on this host, or null for the main domain / unknown hosts. */
    public static function templateForHost(string $host): ?string
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host));

        return static::hostMap()[$host] ?? null;
    }

    /** Public address of this store (shared with customers). */
    public function publicUrl(): string
    {
        // Scheme and port follow the current request (dev servers run on :8000), else APP_URL.
        $req = app()->bound('request') ? request() : null;
        $app = parse_url(config('app.url'));
        $scheme = $req?->getScheme() ?? ($app['scheme'] ?? 'http');
        $portNo = $req?->getPort() ?? ($app['port'] ?? null);
        $port = $portNo && ! in_array((int) $portNo, [80, 443], true) ? ':'.$portNo : '';
        $host = $this->domain ?: $this->slug.'.'.static::baseDomain();

        return $scheme.'://'.$host.($this->domain ? '' : $port);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Staff accounts assigned to this store. */
    public function owners(): HasMany
    {
        return $this->hasMany(User::class)->whereNotNull('role')->orderBy('name');
    }

    public function templateObject(): ?Template
    {
        $manager = app(TemplateManager::class);

        return $manager->has($this->template) ? $manager->get($this->template) : null;
    }

    public function getTemplateNameAttribute(): string
    {
        return $this->templateObject()?->name() ?? $this->template;
    }

    /** True for service-business templates (bookings, services) rather than catalogue stores. */
    public function supportsServices(): bool
    {
        return (bool) $this->templateObject()?->supportsServices();
    }

    /** Ensure every registered template has a storefront row (called on install/listing). */
    public static function syncWithTemplates(): void
    {
        foreach (app(TemplateManager::class)->all() as $template) {
            static::firstOrCreate(['template' => $template->id()], [
                'name' => $template->name().($template->supportsServices() ? '' : ' Store'),
                'slug' => Str::slug($template->id()),
                'is_active' => true,
            ]);
        }
    }
}
