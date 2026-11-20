<?php

namespace App\Models;

use App\Clients\ClientContext;
use App\Clients\StoreFeatures;
use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\GeneratesSlug;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesClientConnection;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A brand in a store's brand master. Products point at one (products.brand_id) and also keep
 * its name in products.brand, which the shop's Brand filter, search and cards read; renaming a
 * brand renames it on its products. Each brand has a storefront page, /brands/{slug}: the shop
 * listing of its products, with its own search & social fields (SEO → Search listings).
 */
class Brand extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;

    // After BelongsToClient: its saving hook fills client_id, which the unique slug needs.
    use GeneratesSlug;
    use HasSeo;
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return Media::url($this->logo);
    }

    public function getUrlAttribute(): string
    {
        return route('brands.show', $this->slug);
    }

    /**
     * Where a brand name written on a product links to: its brand page, or the shop filtered to it
     * when the name has no brand in the master or the Brands module is off for this client.
     */
    public static function urlFor(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        // One query per request: every brand's slug by its lower-case name, for this client.
        $binding = 'brands.slugs.'.(app(ClientContext::class)->client()?->getKey() ?? 0);
        if (! app()->bound($binding)) {
            app()->instance($binding, StoreFeatures::enabled('brands')
                ? static::query()->get(['name', 'slug'])->mapWithKeys(fn (self $b) => [mb_strtolower($b->name) => $b->slug])->all()
                : []);
        }
        $slug = app($binding)[mb_strtolower($name)] ?? null;

        return $slug ? route('brands.show', $slug) : route('shop.index', ['brand' => $name]);
    }

    /**
     * The brands strip on a homepage: each brand's name and its number of products on sale, as
     * {brand, products}. In the order set in the brands list (then A to Z), only brands with
     * something on sale. Without the Brands module the brand is typed on each product, so there is
     * no order to follow: the brands with the most products come first.
     */
    public static function strip(int $limit = 12): Collection
    {
        if (StoreFeatures::enabled('brands')) {
            return static::query()
                ->withCount(['products' => fn (Builder $q) => $q->active()])
                ->whereHas('products', fn (Builder $q) => $q->active())
                ->ordered()
                ->limit($limit)
                ->get()
                ->map(fn (self $b) => (object) ['brand' => $b->name, 'products' => $b->products_count]);
        }

        return Product::active()->whereNotNull('brand')->where('brand', '!=', '')
            ->selectRaw('brand, count(*) as products')->groupBy('brand')
            ->reorder()->orderByDesc('products')->limit($limit)->get()
            ->map(fn ($row) => (object) ['brand' => $row->brand, 'products' => (int) $row->products]);
    }

    protected function seoFallbackImage(): ?string
    {
        return $this->logo;
    }

    /**
     * The client's brand with this name (matched on its slug), created if it has none yet.
     * Used when only the brand's name is written on a product (seeders, older code).
     */
    public static function forName(string $name, ?int $clientId, ?string $template): ?self
    {
        $name = trim($name);
        $slug = Str::slug($name);
        if ($slug === '') {
            return null;
        }

        return static::withoutGlobalScopes()->withTrashed()->where('client_id', $clientId)->where('slug', $slug)->first()
            ?? static::withoutGlobalScopes()->create(['client_id' => $clientId, 'template' => $template, 'name' => $name, 'slug' => $slug]);
    }

    protected static function booted(): void
    {
        // Products carry the name too: keep it in step with the master.
        static::saved(function (Brand $brand) {
            if ($brand->wasChanged('name')) {
                Product::withoutGlobalScopes()->where('brand_id', $brand->getKey())->update(['brand' => $brand->name]);
            }
        });

        // A brand deleted for good leaves its products without a brand.
        static::forceDeleted(function (Brand $brand) {
            Product::withoutGlobalScopes()->where('brand_id', $brand->getKey())->update(['brand_id' => null, 'brand' => null]);
        });
    }
}
