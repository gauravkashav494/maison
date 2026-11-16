<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesClientConnection;
use App\Support\ColourNames;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
    use HasSeo;
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    protected $casts = [
        'price' => 'float',
        'compare_at_price' => 'float',
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

    /**
     * Every word of the term must match one of the product's fields, so technical searches
     * such as "1 inch PVC pipe" or "brass ball valve" work across name, brand, code,
     * material, sizes and category. Variants are searched too, so a variant's own code
     * (GL-NT-XL) finds its product.
     */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim((string) $term))));

        return $q->where(function (Builder $outer) use ($words) {
            foreach ($words as $word) {
                $outer->where(fn (Builder $w) => $w->whereLike('name', $word)
                    ->orWhereLike('description', $word)
                    ->orWhereLike('brand', $word)
                    ->orWhereLike('sku', $word)
                    ->orWhereLike('material', $word)
                    ->orWhereLike('sizes', $word)
                    ->orWhereLike('variants', $word)
                    ->orWhereHas('category', fn ($c) => $c->whereLike('name', $word)));
            }
        });
    }

    /** Ordered public URLs for all images. */
    /** Images are optional: a product without any shows one neutral placeholder, so no view renders a broken image. */
    public const PLACEHOLDER_IMAGE = 'images/product-placeholder.svg';

    public function getImageUrlsAttribute(): array
    {
        $urls = array_values(array_filter(array_map(fn ($p) => Media::url($p), $this->images ?? [])));

        return $urls ?: [asset(self::PLACEHOLDER_IMAGE)];
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

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            $product->syncVariantData();
            $product->syncReviewBaseline();
        });
    }

    /**
     * The shown rating and review count combine two sources: the baseline an admin types in
     * (reviews collected elsewhere) and the approved reviews customers leave here. A review
     * left on the storefront therefore adds to the typed figures instead of replacing them.
     *
     * @return array{rating: float, review_count: int}
     */
    public function combinedReviewStats(): array
    {
        $baseCount = (int) $this->base_review_count;
        $baseTotal = (float) $this->base_rating * $baseCount;

        $real = $this->exists
            ? Review::approved()->where('product_id', $this->getKey())->reorder()
                ->selectRaw('count(*) as n, coalesce(sum(rating), 0) as total')->first()
            : null;
        $count = $baseCount + (int) ($real->n ?? 0);

        return [
            'rating' => $count > 0 ? round(($baseTotal + (float) ($real->total ?? 0)) / $count, 1) : 0.0,
            'review_count' => $count,
        ];
    }

    /**
     * Writing rating / review_count directly (a seeder, an import) sets the baseline, since
     * those columns only ever hold the combined figures; either way they are then recomputed.
     */
    private function syncReviewBaseline(): void
    {
        if ($this->isDirty('review_count') && ! $this->isDirty('base_review_count')) {
            $this->base_review_count = max(0, (int) $this->review_count);
        }
        if ($this->isDirty('rating') && ! $this->isDirty('base_rating')) {
            $this->base_rating = (float) $this->rating;
        }

        if (! $this->exists || $this->isDirty(['base_rating', 'base_review_count'])) {
            $this->forceFill($this->combinedReviewStats());
        }
    }

    /** "Size" or "Sizes", in any case. */
    public static function isSizeAttribute(?string $name): bool
    {
        return (bool) preg_match('/^sizes?$/i', trim((string) $name));
    }

    /** "Colour", "Color", "Colours" or "Colors", in any case. */
    public static function isColourAttribute(?string $name): bool
    {
        return (bool) preg_match('/^colou?rs?$/i', trim((string) $name));
    }

    /** Two attribute names are the same choice: case aside, Colour and Color are one. */
    public static function sameAttribute(string $a, string $b): bool
    {
        $canon = fn (string $n) => self::isColourAttribute($n) ? 'colour' : (self::isSizeAttribute($n) ? 'size' : mb_strtolower(trim($n)));

        return $canon($a) === $canon($b);
    }

    /**
     * Keeps what is derived from the options in step when they are defined:
     *  - the size and colour lists, which every template's own Size and Colour pickers render
     *    (without this, a Size or Colour entered as an option never showed on the storefront);
     *  - each combination's chosen values, dropping any for an option that no longer exists,
     *    so a renamed or removed option cannot leave a combination that never matches.
     */
    public function syncVariantData(): void
    {
        $attributes = [];
        foreach ((array) $this->variant_attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $values = array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), (array) ($attribute['values'] ?? [])), 'strlen')));
            if ($name !== '' && $values) {
                $attributes[] = ['name' => $name, 'values' => $values, 'swatches' => (array) ($attribute['swatches'] ?? [])];
            }
        }
        if (! $attributes) {
            return; // An older product: the size and colour lists are its options.
        }

        $size = collect($attributes)->first(fn ($a) => self::isSizeAttribute($a['name']));
        $colour = collect($attributes)->first(fn ($a) => self::isColourAttribute($a['name']));
        // Only a list with a matching option is rewritten: a product may keep an older list
        // that is not a choice (one display colour beside a Size option, say).
        if ($size) {
            $this->sizes = $size['values'];
        }
        if ($colour) {
            $this->colors = array_map(fn ($value) => ['name' => $value, 'hex' => filled($colour['swatches'][$value] ?? null) ? $colour['swatches'][$value] : ColourNames::hex($value)], $colour['values']);
        }

        $names = array_column($attributes, 'name');
        $this->variants = array_values(array_map(function ($variant) use ($names) {
            $options = [];
            foreach ((array) ($variant['options'] ?? []) as $name => $value) {
                if (in_array($name, $names, true) && filled($value)) {
                    $options[$name] = $value;
                }
            }

            return ['options' => $options] + (array) $variant;
        }, (array) $this->variants));
    }

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
            // Only swatches for values still offered, and only ones with a colour set.
            $swatches = array_filter(
                array_intersect_key((array) ($attribute['swatches'] ?? []), array_flip($values)),
                fn ($hex) => filled($hex),
            );
            // A colour option's values named like a colour ("Red", "Navy blue") get that swatch
            // unless one was picked.
            if (self::isColourAttribute($name)) {
                foreach ($values as $value) {
                    $swatches[$value] ??= ColourNames::hex($value);
                }
                $swatches = array_filter($swatches);
            }
            $attributes[] = ['name' => $name, 'values' => $values, 'swatches' => $swatches];
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
     * @return array<int, array{options:array<string,string>, sku:?string, price:int, stock:int, is_active:bool, label:string, images:array<int,string>}>
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
                'price' => $price === null || $price === '' ? (float) $this->price : round((float) $price, 2),
                'stock' => $stock === null || $stock === '' ? (int) $this->stock : (int) $stock,
                'is_active' => (bool) ($variant['is_active'] ?? true),
                'label' => implode(' / ', array_values($options)),
                // Photos of this combination (URLs); the gallery switches to them when it is chosen.
                'images' => array_values(array_filter(array_map(fn ($path) => is_string($path) ? Media::url($path) : null, (array) ($variant['images'] ?? [])))),
            ];
        }

        return $variants;
    }

    /**
     * The variants for the storefront's live price display (the `variantPricing` Alpine
     * component), each with its price already formatted. Empty when not sold by variant.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pricingVariants(): array
    {
        return $this->sellsVariants()
            ? array_map(fn (array $variant) => $variant + ['price_formatted' => money($variant['price'])], $this->variantList())
            : [];
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
                if (self::sameAttribute((string) $name, $attribute['name'])) {
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
    public function priceFor(array $options = []): float
    {
        return (float) ($this->sellsVariants() ? ($this->findVariant($options)['price'] ?? $this->price) : $this->price);
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
            // Combinations with photos of their own: [{options: {Colour: 'Navy'}, images: [url…]}].
            'variant_images' => array_values(array_map(
                fn (array $v) => ['options' => $v['options'], 'images' => $v['images']],
                array_filter($this->variantList(), fn (array $v) => $v['is_active'] && $v['images'] !== []),
            )),
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
