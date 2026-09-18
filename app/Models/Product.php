<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\HasSeo;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTemplate;

    use HasSeo;

    protected $guarded = [];

    protected $casts = [
        'images' => 'array',
        'colors' => 'array',
        'sizes' => 'array',
        'is_active' => 'boolean',
        'is_new' => 'boolean',
        'is_best_seller' => 'boolean',
        'noindex' => 'boolean',
        'is_veg' => 'boolean',
        'dietary_tags' => 'array',
        'nutrition' => 'array',
        'rating' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)->withPivot('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeNewArrivals(Builder $q): Builder
    {
        return $q->where('is_new', true);
    }

    public function scopeBestSellers(Builder $q): Builder
    {
        return $q->where('is_best_seller', true);
    }

    public function scopeOnSale(Builder $q): Builder
    {
        return $q->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price');
    }

    /** Ordered public URLs for all images. */
    public function getImageUrlsAttribute(): array
    {
        return array_values(array_filter(array_map(fn ($p) => Media::url($p), $this->images ?? [])));
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_urls[0] ?? null;
    }

    public function getHoverImageUrlAttribute(): ?string
    {
        return $this->image_urls[1] ?? null;
    }

    public function getDiscountPercentAttribute(): int
    {
        if (! $this->compare_at_price || $this->compare_at_price <= $this->price) {
            return 0;
        }

        return (int) round((1 - $this->price / $this->compare_at_price) * 100);
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->discount_percent > 0;
    }

    public function getInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    /** Pack size shown on grocery cards: the only size, or null when the product has variants. */
    public function getUnitAttribute(): ?string
    {
        $sizes = $this->sizes ?? [];

        return count($sizes) === 1 ? $sizes[0] : null;
    }

    public function getUrlAttribute(): string
    {
        return route('products.show', $this->slug);
    }

    /** Compact representation used by the storefront JS (quick view, search, cart). */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'url' => $this->url,
            'name' => $this->name,
            'category' => $this->category?->name,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'price_formatted' => money($this->price),
            'compare_at_price_formatted' => $this->compare_at_price ? money($this->compare_at_price) : null,
            'discount_percent' => $this->discount_percent,
            'images' => $this->image_urls,
            'colors' => $this->colors ?? [],
            'sizes' => $this->sizes ?? [],
            'rating' => $this->rating,
            'review_count' => $this->review_count,
            'is_new' => $this->is_new,
            'in_stock' => $this->in_stock,
            'description' => $this->description,
            'brand' => $this->brand,
            'video_url' => $this->video_url,
            // Grocery attributes (null when not applicable)
            'is_veg' => $this->is_veg,
            'unit' => $this->unit,
            'max_qty' => $this->max_qty,
            'dietary_tags' => $this->dietary_tags ?? [],
        ];
    }

    protected function seoFallbackImage(): ?string
    {
        return $this->images[0] ?? null;
    }
}
