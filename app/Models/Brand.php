<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\UsesClientConnection;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A brand in a store's brand master. Products point at one (products.brand_id) and also keep
 * its name in products.brand, which the shop's Brand filter, search and cards read; renaming a
 * brand renames it on its products.
 */
class Brand extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
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
