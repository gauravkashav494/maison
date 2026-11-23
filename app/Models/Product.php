<?php

namespace App\Models;

use App\Clients\ClientQuota;
use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\GeneratesSlug;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\TracksSyncVersion;
use App\Models\Concerns\UsesClientConnection;
use App\Support\Activity\ActivityLog;
use App\Support\ColourNames;
use App\Support\Media;
use App\Support\TaxPrices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Product extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
    use GeneratesSlug;
    use HasSeo;
    use SoftDeletes;
    use UsesClientConnection;

    use TracksSyncVersion;

    /** EcomVision-owned fields the local system is told about. */
    public const SYNC_TRACKED = ['slug', 'description', 'is_active', 'has_variants', 'variant_attributes'];

    protected $guarded = [];

    /**
     * Also resends the product when a variant is added, removed or renamed (its SKU or options),
     * but not when only variant stock, price, images or availability change: stock moves on
     * every sale and every stock push, and the local system owns price.
     */
    protected function hasSyncRelevantChanges(): bool
    {
        if (array_intersect(array_keys($this->getChanges()), self::SYNC_TRACKED)) {
            return true;
        }
        if (! array_key_exists('variants', $this->getChanges())) {
            return false;
        }

        $shape = fn ($variants) => array_map(
            fn ($variant) => [(string) ($variant['sku'] ?? ''), (array) ($variant['options'] ?? [])],
            array_values((array) $variants),
        );

        return $shape($this->getOriginal('variants')) != $shape($this->variants);
    }

    protected $casts = [
        'price' => 'float',
        'compare_at_price' => 'float',
        'tax_rate' => 'float',
        'price_incl_tax' => 'float',
        'price_excl_tax' => 'float',
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

    /**
     * The main category: the breadcrumb, the card label and "related products". It is always
     * one of categories() as well (kept so by the saved hook below).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Every category the product is listed under, the main one included. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Sets the other categories this product is listed under, besides its main one (which is
     * always kept), and writes the activity line: a pivot sync fires no model events.
     *
     * $mainId is the main category being saved with them. The product form passes it, because
     * Filament saves relationships before it writes the record's own columns.
     *
     * @param  array<int|string>  $categoryIds
     */
    public function listUnder(array $categoryIds, ?int $mainId = null): void
    {
        $main = $mainId ?? (int) $this->category_id;
        // The main category on either side of this save is not an "other" category.
        $mains = array_values(array_filter([$main, (int) $this->category_id, (int) ($this->getPrevious()['category_id'] ?? 0)]));
        $others = fn () => $this->categories()->whereKeyNot($mains)->orderBy('name')->pluck('name')->all();
        $before = $others();

        $ids = array_map('intval', array_filter($categoryIds, 'filled'));
        $this->categories()->sync(array_values(array_unique(array_filter([$main, ...$ids]))));

        $after = $others();
        if ($before === $after) {
            return;
        }

        $list = fn (array $names) => $names === [] ? 'none' : implode(', ', $names);
        $log = app(ActivityLog::class);
        $log->record(
            'updated',
            'changed the other categories of '.$log->describer()->reference($this).' from '.$list($before).' to '.$list($after),
            $this,
            [['field' => 'categories', 'label' => 'also listed under', 'old' => $list($before), 'new' => $list($after)]],
        );
    }

    /** Products listed under any of these categories (not only those whose main category it is). */
    public function scopeInCategories(Builder $q, $categoryIds): Builder
    {
        return $q->whereIn($q->qualifyColumn('id'), fn ($sub) => $sub->select('product_id')
            ->from('category_product')->whereIn('category_id', $categoryIds));
    }

    /**
     * The product's brand in the store's brand master (brand_id). Not called brand(): that is
     * the column holding the brand's name, which every view reads as text.
     */
    public function brandRecord(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)->withPivot('sort_order');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
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
        return $q->whereNotNull('compare_at_price')->whereRaw('compare_at_price > '.self::mrpBasisSql($q->getModel()));
    }

    /**
     * SQL for the price an MRP is measured against (see mrpBasis()): the price including tax.
     * Before-tax stores read the stored price_incl_tax; a database without that column yet
     * falls back to `price`.
     */
    public static function mrpBasisSql(?self $model = null): string
    {
        $model ??= new static;

        return ! prices_include_tax() && self::hasTaxPriceColumns($model) ? 'coalesce(price_incl_tax, price)' : 'price';
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
                    ->orWhereHas('categories', fn ($c) => $c->whereLike('name', $word)));
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

    /** The discount shown on cards and badges: of the price displayPricing() shows. */
    public function getDiscountPercentAttribute(): int
    {
        return $this->displayPricing()['discount_percent'];
    }

    /** The price a card shows: the cheapest variant's for a product sold by variant. */
    public function getDisplayPriceAttribute(): float
    {
        return $this->displayPricing()['price'];
    }

    /** The "was" price beside display_price, or null when it shows no discount. */
    public function getDisplayCompareAtPriceAttribute(): ?float
    {
        return $this->displayPricing()['compare_at_price'];
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

    // ---- Tax ---------------------------------------------------------------

    /** Whether each connection's products table has the price incl./excl. tax columns yet. */
    private static array $hasTaxPriceColumns = [];

    /**
     * The price an MRP ("was" price) is compared with: the price including tax, because an MRP in
     * India always includes it. Same as $price when the store's prices include tax; otherwise
     * $price plus this product's tax, so "% off" and "Save" are not overstated.
     */
    public function mrpBasis(float $price): float
    {
        return prices_include_tax() ? $price : TaxPrices::convert($price, $this->effectiveTaxRate(), toInclusive: true);
    }

    private static function hasTaxPriceColumns(self $model): bool
    {
        $connection = $model->getConnectionName() ?? 'default';

        return self::$hasTaxPriceColumns[$connection] ??= Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), 'price_incl_tax');
    }

    /** The product's tax rate: its tax's (copied to tax_rate on save); a product with no tax is charged 0%. */
    public function effectiveTaxRate(): float
    {
        return $this->tax_id ? (float) ($this->tax_rate ?? 0) : 0.0;
    }

    /**
     * Keeps price_incl_tax / price_excl_tax (and each variant's) in line with `price` and the tax
     * rate, whatever saved the product (the form, an import, a seeder). `price` stays the price
     * the cart charges: the one the store's prices are entered as.
     */
    private function syncTaxPrices(): void
    {
        if (! self::hasTaxPriceColumns($this)) {
            return;
        }

        // The rate comes from the tax: copied here so the cart and invoices read it directly.
        // No tax: no rate, so a rate left from an earlier tax is not charged.
        if (! $this->tax_id) {
            $this->tax_rate = null;
        } elseif ($tax = Tax::withoutGlobalScopes()->withTrashed()->find($this->tax_id)) {
            $this->tax_rate = (float) $tax->rate;
        }

        $inclusive = prices_include_tax();
        $rate = $this->effectiveTaxRate();

        $synced = TaxPrices::sync([
            'price' => $this->price,
            'price_incl_tax' => $this->price_incl_tax,
            'price_excl_tax' => $this->price_excl_tax,
        ], $rate, $inclusive);
        $this->price_incl_tax = $synced['price_incl_tax'];
        $this->price_excl_tax = $synced['price_excl_tax'];

        // Variants follow the product's tax: one rate for all of them.
        if (is_array($this->variants) && $this->variants !== []) {
            $this->variants = array_map(
                fn ($row) => is_array($row) ? TaxPrices::sync(array_diff_key($row, ['tax_rate' => true]), $rate, $inclusive) : $row,
                $this->variants,
            );
        }
    }

    // ---- Variants ----------------------------------------------------------

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            $product->assignColourPhotos();
            $product->syncVariantData();
            $product->syncTaxPrices();
            $product->syncReviewBaseline();
            $product->syncBrand();
            $product->lowest_stock = $product->lowestStock();
        });

        // The stock history (Inventory): what a new product starts with, and every stock change
        // made on the product form. Sales, cancellations and Inventory adjustments are written
        // by adjustStock(), which saves quietly so they are not counted twice.
        static::created(fn (Product $product) => $product->logStockChanges([], 0, StockMovement::OPENING));
        static::updated(function (Product $product) {
            if ($product->wasChanged(['stock', 'variants', 'has_variants'])) {
                $product->logStockChanges(
                    $product->getOriginal('has_variants') ? (array) $product->getOriginal('variants') : [],
                    (int) $product->getOriginal('stock'),
                    StockMovement::EDITED,
                );
            }
        });

        // The main category is always one of the categories the product is listed under, also
        // when only category_id is written (seeders, imports, older code).
        static::saved(function (Product $product) {
            if ($product->category_id && ($product->wasRecentlyCreated || $product->wasChanged('category_id'))) {
                $product->categories()->syncWithoutDetaching([$product->category_id]);
            }
        });

        // Images (and storage) used read from a short cache; recount after any change, so the
        // usage bar and "Product images used" show the save at once, not up to 30 seconds later.
        $recount = fn (Product $product) => $product->client_id && ClientQuota::forgetFor((int) $product->client_id);
        static::saved($recount);
        static::deleted($recount);
        static::restored($recount);
        static::forceDeleted($recount);
    }

    /**
     * Keeps brand_id (the brand master) and brand (its name, read by the shop) in step. Choosing
     * a brand writes its name; writing only a name (seeders, older code) links, or creates, the
     * client's brand of that name; clearing either clears both.
     */
    private function syncBrand(): void
    {
        if ($this->isDirty('brand_id')) {
            $this->brand = $this->brand_id
                ? Brand::withoutGlobalScopes()->withTrashed()->whereKey($this->brand_id)->value('name')
                : null;

            return;
        }

        if ($this->isDirty('brand')) {
            $this->brand_id = filled($this->brand)
                ? Brand::forName((string) $this->brand, $this->client_id, $this->template)?->getKey()
                : null;
            $this->brand = $this->brand_id ? Brand::withoutGlobalScopes()->withTrashed()->whereKey($this->brand_id)->value('name') : null;
        }
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
    /**
     * Photos per colour, filled two ways besides picking them in the form:
     *
     *  1. files uploaded under a colour (Variants → Colour → Upload) arrive as
     *     variant_attributes.<n>.uploads.<Colour>: they join the product's images and are ticked
     *     for that colour;
     *  2. a newly added image whose file name names exactly one colour ("container-red-1.jpg")
     *     is ticked for it, as large stores sort studio photos. Images the product already had
     *     are left alone, so a photo the store unticked stays unticked.
     */
    public function assignColourPhotos(): void
    {
        $attributes = (array) $this->variant_attributes;
        $images = array_values(array_filter((array) $this->images, 'is_string'));
        $colourKey = collect($attributes)->search(fn ($a) => is_array($a) && self::isColourAttribute((string) ($a['name'] ?? '')));

        foreach ($attributes as $key => $attribute) {
            if (! is_array($attribute) || ! array_key_exists('uploads', $attribute)) {
                continue;
            }
            foreach ((array) $attribute['uploads'] as $value => $paths) {
                foreach (array_filter((array) $paths, 'is_string') as $path) {
                    in_array($path, $images, true) || $images[] = $path;
                    if ($key === $colourKey) {
                        $attributes[$key]['images'][$value] = array_values(array_unique([...(array) ($attributes[$key]['images'][$value] ?? []), $path]));
                    }
                }
            }
            unset($attributes[$key]['uploads']);
        }

        if ($colourKey !== false) {
            $before = array_filter((array) ($this->getOriginal('images') ?? []), 'is_string');
            $assigned = collect((array) ($attributes[$colourKey]['images'] ?? []))->flatten()->all();
            $values = array_values(array_filter(array_map('strval', (array) ($attributes[$colourKey]['values'] ?? [])), 'strlen'));

            foreach (array_diff($images, $before, $assigned) as $path) {
                $words = '-'.Str::slug(pathinfo($path, PATHINFO_FILENAME)).'-';
                $matches = array_values(array_filter($values, fn ($value) => Str::slug($value) !== '' && str_contains($words, '-'.Str::slug($value).'-')));
                // "navy-blue" also contains "blue": a colour inside a longer one named is dropped;
                // two separate colours named means unclear, so the photo is left for the store.
                $matches = array_values(array_filter($matches, fn ($m) => ! collect($matches)->contains(fn ($other) => $other !== $m && str_contains('-'.Str::slug($other).'-', '-'.Str::slug($m).'-'))));
                if (count($matches) === 1) {
                    $attributes[$colourKey]['images'][$matches[0]] = [...(array) ($attributes[$colourKey]['images'][$matches[0]] ?? []), $path];
                }
            }
        }

        $this->images = $images;
        $this->variant_attributes = $attributes;
    }

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

    /**
     * Photos per value of the colour option (the first one, as on most stores), e.g.
     * [{options: {Colour: 'Red'}, images: [url…]}]. Only images the product still has are used,
     * so removing a photo in the Media tab never leaves a broken one behind.
     *
     * @return array<int, array{options: array<string, string>, images: array<int, string>}>
     */
    public function optionImages(): array
    {
        $own = array_flip(array_filter((array) $this->images, 'is_string'));

        foreach ((array) $this->variant_attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            if (! self::isColourAttribute($name)) {
                continue;
            }
            $values = array_values(array_filter(array_map('strval', (array) ($attribute['values'] ?? [])), 'strlen'));
            $sets = [];
            foreach ($values as $value) {
                $paths = array_values(array_filter((array) (($attribute['images'] ?? [])[$value] ?? []), fn ($p) => is_string($p) && isset($own[$p])));
                $urls = array_values(array_filter(array_map(fn (string $p) => Media::url($p), $paths)));
                if ($name !== '' && $urls !== []) {
                    $sets[] = ['options' => [$name => $value], 'images' => $urls];
                }
            }

            return $sets; // one colour option drives the photos
        }

        return [];
    }

    /**
     * The colours shown on a product card: each value of the colour option with its swatch and
     * its first photo (null when none is picked), so hovering a swatch can show that colour.
     *
     * @return array<int, array{name: string, hex: ?string, image: ?string}>
     */
    public function cardColours(): array
    {
        $colour = collect($this->variantAttributes())->first(fn (array $a) => self::isColourAttribute($a['name']));
        if (! $colour) {
            return [];
        }
        $photos = collect($this->optionImages())->mapWithKeys(fn (array $set) => [reset($set['options']) => $set['images'][0] ?? null]);

        return array_map(fn (string $value) => [
            'name' => $value,
            'hex' => $colour['swatches'][$value] ?? null,
            'image' => $photos[$value] ?? null,
        ], $colour['values']);
    }

    /** Attribute names, in the order the customer chooses them. */
    public function variantAttributeNames(): array
    {
        return array_column($this->variantAttributes(), 'name');
    }

    /**
     * Moves stock by $delta (negative when sold, positive when an order is cancelled and the
     * goods come back) and writes the move to the stock history with $reason. A product sold by
     * variant has its stock on the variant, so that is adjusted as well as the product total.
     * Stock never goes below zero.
     *
     * The row is read again under a lock, so two moves at once cannot both start from the same
     * stock. It is saved quietly: the movement is the record of it, and the staff action behind
     * it (if any) writes its own activity line. Returns the stock after the move (the variant's,
     * when one was moved).
     */
    public function adjustStock(int $delta, array $options = [], string $reason = StockMovement::EDITED, ?Order $order = null, ?string $note = null): int
    {
        return $this->getConnection()->transaction(function () use ($delta, $options, $reason, $order, $note) {
            $fresh = static::withoutGlobalScopes()->withTrashed()->lockForUpdate()->find($this->getKey()) ?? $this;
            [$index, $variantOptions] = $fresh->variantIndex($options);

            if ($index !== null) {
                $variants = (array) $fresh->variants;
                $before = (int) ($variants[$index]['stock'] ?? $fresh->stock);
                $after = max(0, $before + $delta);
                $variants[$index]['stock'] = $after;
                $fresh->variants = $variants;
                $fresh->stock = max(0, (int) $fresh->stock + ($after - $before));
            } else {
                $before = (int) $fresh->stock;
                $after = max(0, $before + $delta);
                $fresh->stock = $after;
            }

            if ($after !== $before) {
                $fresh->lowest_stock = $fresh->lowestStock();
                $fresh->saveQuietly();
            }

            StockMovement::log($fresh, $after - $before, $after, $reason, $variantOptions, $order, $note);

            // This copy reads the stock as saved.
            $keys = ['stock', 'variants', 'lowest_stock'];
            $this->setRawAttributes(array_intersect_key($fresh->getAttributes(), array_flip($keys)) + $this->getAttributes());
            $this->syncOriginalAttributes($keys);

            return $after;
        });
    }

    /**
     * Where the variant with these options sits in `variants`, and its options as stored; [null,
     * null] for a product not sold by variant or options it does not have. Inactive variants are
     * found too: their stock can still be counted and corrected.
     *
     * @return array{0: ?int, 1: ?array<string, string>}
     */
    public function variantIndex(array $options): array
    {
        if (! $options || ! $this->sellsVariants()) {
            return [null, null];
        }

        $wanted = $this->normaliseOptions($options);
        foreach ((array) $this->variants as $index => $variant) {
            $own = $this->normaliseOptions((array) ($variant['options'] ?? []));
            if ($own && $own == $wanted) {
                return [$index, $own];
            }
        }

        return [null, null];
    }

    /** The stock of the lowest variant on sale, or the product's own stock: what "low stock" goes by. */
    public function lowestStock(): int
    {
        if ($this->sellsVariants()) {
            $stocks = array_column(array_filter($this->variantList(), fn (array $v) => $v['is_active']), 'stock');
            if ($stocks) {
                return (int) min($stocks);
            }
        }

        return (int) $this->stock;
    }

    /**
     * Writes the difference between the stock before ($oldVariants / $oldStock) and now to the
     * stock history: per variant for a product sold by variant, else on the product's stock.
     */
    private function logStockChanges(array $oldVariants, int $oldStock, string $reason): void
    {
        $now = $this->sellsVariants() ? $this->variantStocks((array) $this->variants, (int) $this->stock) : [];
        $was = $this->variantStocks($oldVariants, $oldStock);

        // Sold by variant before and after: what changed on each combination.
        if ($now && ($was || $reason === StockMovement::OPENING)) {
            foreach ($now + $was as $key => $row) {
                $before = $was[$key]['stock'] ?? 0;
                $after = $now[$key]['stock'] ?? 0;
                StockMovement::log($this, $after - $before, $after, $reason, $row['options']);
            }

            return;
        }

        StockMovement::log($this, (int) $this->stock - $oldStock, (int) $this->stock, $reason);
    }

    /** @return array<string, array{options: array<string, string>, stock: int}> keyed by the options */
    private function variantStocks(array $variants, int $fallback): array
    {
        $rows = [];
        foreach ($variants as $variant) {
            $options = array_filter(array_map(fn ($v) => trim((string) $v), (array) ($variant['options'] ?? [])), 'strlen');
            if (! $options) {
                continue;
            }
            $stock = $variant['stock'] ?? null;
            $rows[json_encode($options)] = ['options' => $options, 'stock' => $stock === null || $stock === '' ? $fallback : max(0, (int) $stock)];
        }

        return $rows;
    }

    /**
     * Locks these lines' products for the rest of the current transaction and checks each line
     * against the stock as it is now, so two sales of the last few units cannot both read the
     * same stock. A product sold by variant is counted per variant; one that is not shares one
     * stock across its lines (e.g. two sizes of the same plain product). Used when an order is
     * placed (PlaceOrder).
     *
     * @param  array<string|int, array{product_id:int, options:array, qty:int}>  $lines
     *                                                                                   The result holds the locked products keyed by id, and line key => how many are left for
     *                                                                                   each line that asks for more than that.
     * @return array{products: \Illuminate\Database\Eloquent\Collection<int, static>, short: array<string|int, int>}
     */
    public static function lockForLines(array $lines): array
    {
        $products = static::whereIn('id', array_column($lines, 'product_id'))
            ->lockForUpdate()->get()->keyBy('id');

        $left = [];
        $short = [];
        foreach ($lines as $key => $line) {
            $product = $products[$line['product_id']] ?? null;
            $options = (array) ($line['options'] ?? []);
            $bucket = $product?->sellsVariants() ? $product->id.'|'.json_encode($product->normaliseOptions($options)) : (string) $product?->id;
            $left[$bucket] ??= $product ? max(0, $product->stockFor($options)) : 0;

            if ((int) $line['qty'] > $left[$bucket]) {
                $short[$key] = $left[$bucket];
            }
            $left[$bucket] = max(0, $left[$bucket] - (int) $line['qty']);
        }

        return ['products' => $products, 'short' => $short];
    }

    /** True when this product sells per variant (price, SKU and stock come from the variant). */
    public function sellsVariants(): bool
    {
        return (bool) $this->has_variants && $this->variantList() !== [];
    }

    /**
     * The variant combinations, normalised. A variant with no price of its own inherits the
     * product's price; the same goes for compare-at price, stock and SKU. The compare-at
     * price is null unless it is above the variant's price, so a variant priced above the
     * product's compare-at price shows no discount.
     *
     * @return array<int, array{options:array<string,string>, sku:?string, price:float, compare_at_price:?float, stock:int, is_active:bool, label:string, images:array<int,string>}>
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
            $price = $price === null || $price === '' ? (float) $this->price : round((float) $price, 2);
            $compareAt = $variant['compare_at_price'] ?? null;
            $compareAt = $compareAt === null || $compareAt === '' ? (float) $this->compare_at_price : round((float) $compareAt, 2);
            $stock = $variant['stock'] ?? null;
            $variants[] = [
                'options' => $options,
                'sku' => filled($variant['sku'] ?? null) ? (string) $variant['sku'] : $this->sku,
                'price' => $price,
                'compare_at_price' => $compareAt > $price ? $compareAt : null,
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
     * component), each with its price, compare-at price and saving already formatted
     * (see pricing()). Empty when not sold by variant.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pricingVariants(): array
    {
        return $this->sellsVariants()
            ? array_map(fn (array $variant) => self::pricing($variant['price'], $variant['compare_at_price'], $this->mrpBasis($variant['price'])) + $variant, $this->variantList())
            : [];
    }

    /**
     * The price shown before a variant is chosen: on cards, in quick view and on the product
     * page. For a product sold by variant it is the cheapest available variant's (one in stock
     * if any is), with that variant's own "was" price, so the card and the page agree with
     * what can be bought; otherwise the product's own price.
     */
    public function displayPricing(): array
    {
        if ($this->sellsVariants()) {
            $active = array_filter($this->variantList(), fn (array $v) => $v['is_active']);
            $inStock = array_filter($active, fn (array $v) => $v['stock'] > 0);
            $pool = $inStock ?: $active;
            if ($pool) {
                usort($pool, fn (array $a, array $b) => $a['price'] <=> $b['price']);

                return self::pricing($pool[0]['price'], $pool[0]['compare_at_price'], $this->mrpBasis($pool[0]['price']));
            }
        }

        return self::pricing((float) $this->price, $this->compare_at_price ? (float) $this->compare_at_price : null, $this->mrpBasis((float) $this->price));
    }

    /**
     * A price with its "was" price for display: the compare-at price, discount and saving are
     * null / 0 unless the compare-at price is above the price. $mrpBasis is what the MRP is
     * measured against (mrpBasis(): the price including tax); $price when not given. The price
     * shown stays $price.
     *
     * @return array{price:float, price_formatted:string, compare_at_price:?float, compare_at_price_formatted:?string, discount_percent:int, save_formatted:?string}
     */
    public static function pricing(float $price, ?float $compareAt, ?float $mrpBasis = null): array
    {
        $basis = $mrpBasis ?? $price;
        $compareAt = $compareAt !== null && $compareAt > $basis ? $compareAt : null;

        return [
            'price' => $price,
            'price_formatted' => money($price),
            'compare_at_price' => $compareAt,
            'compare_at_price_formatted' => $compareAt ? money($compareAt) : null,
            'discount_percent' => $compareAt ? (int) round((1 - $basis / $compareAt) * 100) : 0,
            'save_formatted' => $compareAt ? money($compareAt - $basis) : null,
        ];
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

    /** Compare-at ("was") price of a chosen combination, or null when it shows no discount. */
    public function compareAtFor(array $options = []): ?float
    {
        if (! $this->sellsVariants()) {
            return $this->compare_at_price && $this->compare_at_price > $this->price ? (float) $this->compare_at_price : null;
        }
        $variant = $this->findVariant($options);

        return $variant ? $variant['compare_at_price'] : null;
    }

    /** Price of a chosen combination (the product's own price when it does not sell variants). */
    public function priceFor(array $options = []): float
    {
        return (float) ($this->sellsVariants() ? ($this->findVariant($options)['price'] ?? $this->price) : $this->price);
    }

    /**
     * The most of this choice one order can hold, and why: the product's own "max quantity per
     * order" or the stock of what is chosen, whichever is lower (null when neither applies).
     *
     * @return array{limit: ?int, reason: ?string} reason is 'max' or 'stock'
     */
    public function orderLimit(array $options = []): array
    {
        $max = $this->max_qty ? (int) $this->max_qty : null;
        $stock = max(0, $this->stockFor($options));

        if ($max !== null && $max <= $stock) {
            return ['limit' => $max, 'reason' => 'max'];
        }

        return ['limit' => $stock, 'reason' => 'stock'];
    }

    /** What the customer is told when a quantity goes over orderLimit(); null when it does not. */
    public function orderLimitMessage(int $wanted, array $options = [], int $inCart = 0): ?string
    {
        ['limit' => $limit, 'reason' => $reason] = $this->orderLimit($options);
        if ($limit === null || $wanted <= $limit) {
            return null;
        }
        if ($limit === 0) {
            return 'This item is out of stock.';
        }
        $message = $reason === 'max'
            ? "You can order at most {$limit} of this item."
            : "Only {$limit} left in stock.";

        return $inCart > 0 ? $message." You already have {$inCart} in your cart." : $message;
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
            // What the card shows: the cheapest variant's price and "was" price for a product
            // sold by variant (see displayPricing()).
            ...Arr::only($this->displayPricing(), ['price', 'compare_at_price', 'price_formatted', 'compare_at_price_formatted', 'discount_percent']),
            'images' => $this->image_urls,
            // Photo sets for the gallery, best match wins: the photos chosen per colour (or the
            // one option that changes the photos) first, then combinations with photos of their
            // own, which override them once enough options are picked to match the combination.
            'variant_images' => [
                ...$this->optionImages(),
                ...array_values(array_map(
                    fn (array $v) => ['options' => $v['options'], 'images' => $v['images']],
                    array_filter($this->variantList(), fn (array $v) => $v['is_active'] && $v['images'] !== []),
                )),
            ],
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
