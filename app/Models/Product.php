<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\UsesClientConnection;
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
    use UsesClientConnection;

    use BelongsToClient;

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
        'specifications' => 'array',
        'rating' => 'float',
        'has_variants' => 'boolean',
        'variant_attributes' => 'array',
        'variants' => 'array',
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

    // ---- Variants ----------------------------------------------------------

    /**
     * The attributes this product varies by, in one shape whether they were entered as
     * variant attributes or as the older colour and size lists.
     *
     * @return array<int, array{name:string, values:array<int,string>, swatches:array<string,string>}>
     */
    public function variantAttributes(): array
    {
        $attributes = [];

        foreach ((array) $this->variant_attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $values = array_values(array_filter(array_map('strval', (array) ($attribute['values'] ?? [])), 'strlen'));
            if ($name === '' || ! $values) {
                continue;
            }
            $attributes[] = ['name' => $name, 'values' => $values, 'swatches' => (array) ($attribute['swatches'] ?? [])];
        }

        if ($attributes) {
            return $attributes;
        }

        // Older products: the colour and size lists are the attributes.
        $colours = array_values(array_filter(array_map(fn ($c) => is_array($c) ? ($c['name'] ?? null) : $c, (array) $this->colors)));
        if ($colours) {
            $swatches = [];
            foreach ((array) $this->colors as $colour) {
                if (is_array($colour) && ! empty($colour['name']) && ! empty($colour['hex'])) {
                    $swatches[$colour['name']] = $colour['hex'];
                }
            }
            $attributes[] = ['name' => 'Colour', 'values' => $colours, 'swatches' => $swatches];
        }
        $sizes = array_values(array_filter(array_map('strval', (array) $this->sizes), 'strlen'));
        if ($sizes) {
            $attributes[] = ['name' => 'Size', 'values' => $sizes, 'swatches' => []];
        }

        return $attributes;
    }

    /** Attribute names, in the order the customer chooses them. */
    public function variantAttributeNames(): array
    {
        return array_column($this->variantAttributes(), 'name');
    }

    /** True when this product sells per variant (price, SKU and stock come from the variant). */
    public function sellsVariants(): bool
    {
        return (bool) $this->has_variants && $this->variantList() !== [];
    }

    /**
     * The variant combinations, normalised. A variant with no price of its own inherits the
     * product's price; the same goes for stock and SKU.
     *
     * @return array<int, array{options:array<string,string>, sku:?string, price:int, stock:int, is_active:bool, label:string}>
     */
    public function variantList(): array
    {
        $variants = [];

        foreach ((array) $this->variants as $variant) {
            $options = [];
            foreach ((array) ($variant['options'] ?? []) as $name => $value) {
                $name = trim((string) $name);
                $value = trim((string) $value);
                if ($name !== '' && $value !== '') {
                    $options[$name] = $value;
                }
            }
            if (! $options) {
                continue;
            }
            $price = $variant['price'] ?? null;
            $stock = $variant['stock'] ?? null;
            $variants[] = [
                'options' => $options,
                'sku' => filled($variant['sku'] ?? null) ? (string) $variant['sku'] : $this->sku,
                'price' => $price === null || $price === '' ? (int) $this->price : (int) $price,
                'stock' => $stock === null || $stock === '' ? (int) $this->stock : (int) $stock,
                'is_active' => (bool) ($variant['is_active'] ?? true),
                'label' => implode(' / ', array_values($options)),
            ];
        }

        return $variants;
    }

    /** The variant matching exactly these options, if it is on sale. */
    public function findVariant(array $options): ?array
    {
        $wanted = $this->normaliseOptions($options);
        if (! $wanted) {
            return null;
        }

        foreach ($this->variantList() as $variant) {
            if ($variant['is_active'] && $variant['options'] == $wanted) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Keeps only options this product actually offers, in attribute order, so a value posted
     * by hand cannot end up on an order line.
     *
     * @return array<string,string>
     */
    public function normaliseOptions(array $options): array
    {
        $clean = [];

        foreach ($this->variantAttributes() as $attribute) {
            $value = null;
            foreach ($options as $name => $candidate) {
                if (strcasecmp((string) $name, $attribute['name']) === 0) {
                    $value = (string) $candidate;
                    break;
                }
            }
            if ($value === null) {
                continue;
            }
            foreach ($attribute['values'] as $allowed) {
                if (strcasecmp($allowed, $value) === 0) {
                    $clean[$attribute['name']] = $allowed;
                    break;
                }
            }
        }

        return $clean;
    }

    /** Price of a chosen combination (the product's own price when it does not sell variants). */
    public function priceFor(array $options = []): int
    {
        return (int) ($this->sellsVariants() ? ($this->findVariant($options)['price'] ?? $this->price) : $this->price);
    }

    /** Stock of a chosen combination. */
    public function stockFor(array $options = []): int
    {
        if (! $this->sellsVariants()) {
            return (int) $this->stock;
        }
        $variant = $this->findVariant($options);

        return (int) ($variant ? $variant['stock'] : array_sum(array_column(array_filter($this->variantList(), fn ($v) => $v['is_active']), 'stock')));
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
            'sku' => $this->sku,
            'material' => $this->material,
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
