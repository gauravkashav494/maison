<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        return $this->listing($request, Product::query(), 'Shop', 'All pieces', Seo::simple('Shop all', 'Clothing, fragrance, timepieces, leather goods and accessories.'));
    }

    public function category(Request $request, string $slug): View
    {
        // Merchandising slugs share the /shop/{slug} namespace with categories.
        return match ($slug) {
            'new-arrivals' => $this->listing($request, Product::newArrivals(), 'New Arrivals', 'Just in', Seo::simple('New Arrivals', 'The latest additions to the edit — arriving weekly, in limited quantities.')),
            'best-sellers' => $this->listing($request, Product::bestSellers(), 'Best Sellers', 'Most loved', Seo::simple('Best Sellers', 'The pieces our clients return to — season after season.')),
            'sale' => $this->listing($request, Product::onSale(), 'Sale', 'Reduced', Seo::simple('Sale', 'Selected pieces at reduced prices, while stock lasts.')),
            default => $this->categoryListing($request, Category::active()->where('slug', $slug)->firstOrFail()),
        };
    }

    private function categoryListing(Request $request, Category $category): View
    {
        $ids = [$category->id, ...$category->children()->pluck('id')->all()];

        return $this->listing(
            $request,
            Product::whereIn('category_id', $ids),
            $category->name,
            $category->parent?->name ?? ($category->tagline ?? 'Shop'),
            Seo::forModel($category, $category->url),
            $category,
        );
    }

    private function listing(Request $request, Builder $base, string $title, string $eyebrow, Seo $seo, ?Category $category = null): View
    {
        $base->active();

        // Facet options are computed from the unfiltered scope so users can widen a filter.
        $scope = (clone $base)->with('category')->get(['id', 'category_id', 'price', 'sizes', 'colors', 'material', 'brand', 'rating', 'stock', 'dietary_tags']);
        $facets = $this->facets($scope, $category);

        $f = $this->filtersFrom($request);
        $query = (clone $base)->with('category');

        if ($f['category']) {
            $query->whereIn('category_id', Category::whereIn('slug', $f['category'])->pluck('id'));
        }
        if ($f['subcategory']) {
            $query->whereIn('category_id', Category::whereIn('slug', $f['subcategory'])->pluck('id'));
        }
        if ($f['collection']) {
            $query->whereHas('collections', fn ($c) => $c->whereIn('slug', $f['collection']));
        }
        if ($f['brand']) {
            $query->whereIn('brand', $f['brand']);
        }
        if ($f['material']) {
            $query->whereIn('material', $f['material']);
        }
        if ($f['min'] !== null) {
            $query->where('price', '>=', $f['min']);
        }
        if ($f['max'] !== null) {
            $query->where('price', '<=', $f['max']);
        }
        if ($f['rating']) {
            $query->where('rating', '>=', $f['rating']);
        }
        if ($f['availability'] === 'in-stock') {
            $query->where('stock', '>', 0);
        }
        if ($f['sale']) {
            $query->onSale();
        }
        if ($f['veg']) {
            $query->where('is_veg', true); // grocery templates: vegetarian-only switch
        }
        if ($f['discount']) {
            $query->onSale()->whereRaw('(compare_at_price - price) * 100.0 / nullif(compare_at_price, 0) >= ?', [$f['discount']]);
        }
        foreach ($f['diet'] as $tag) {
            $query->whereLike('dietary_tags', trim(json_encode($tag), '"'));
        }
        // JSON array facets — SQLite/MySQL both support LIKE on the serialised JSON.
        foreach ($f['size'] as $size) {
            $query->whereLike('sizes', trim(json_encode($size), '"'));
        }
        foreach ($f['color'] as $color) {
            $query->whereLike('colors', '"name":'.json_encode($color));
        }

        match ($f['sort']) {
            'price-asc' => $query->orderBy('price'),
            'price-desc' => $query->orderByDesc('price'),
            'newest' => $query->orderByDesc('created_at'),
            'rating' => $query->orderByDesc('rating')->orderByDesc('review_count'),
            default => $query->orderBy('sort_order')->orderBy('name'),
        };

        $activeCount = count(array_filter([
            $f['category'], $f['subcategory'], $f['collection'], $f['brand'], $f['material'], $f['size'], $f['color'],
            $f['min'] !== null || $f['max'] !== null, $f['rating'], $f['availability'], $f['sale'], $f['veg'], $f['diet'], $f['discount'],
        ]));

        return view('shop.index', [
            'title' => $title,
            'eyebrow' => $eyebrow,
            'category' => $category,
            'categories' => Category::active()->topLevel()->where('show_in_menu', true)->get(),
            'products' => $query->paginate(24)->withQueryString(),
            'facets' => $facets,
            'filters' => $f,
            'activeCount' => $activeCount,
            'view' => $request->string('view')->toString() === 'list' ? 'list' : 'grid',
            'seo' => $seo,
        ]);
    }

    private function filtersFrom(Request $request): array
    {
        $list = fn (string $key) => array_values(array_filter(array_map('trim', explode(',', (string) $request->query($key, '')))));

        return [
            'category' => $list('category'),
            'subcategory' => $list('subcategory'),
            'collection' => $list('collection'),
            'brand' => $list('brand'),
            'material' => $list('material'),
            'size' => $list('size'),
            'color' => $list('color'),
            'min' => $request->filled('min') ? (int) $request->query('min') : null,
            'max' => $request->filled('max') ? (int) $request->query('max') : null,
            'rating' => $request->filled('rating') ? (float) $request->query('rating') : null,
            'availability' => $request->query('availability'),
            'sale' => (bool) $request->query('sale'),
            'veg' => (bool) $request->query('veg'),
            'diet' => $list('diet'),
            'discount' => $request->filled('discount') ? (int) $request->query('discount') : null,
            'sort' => (string) $request->query('sort', ''),
        ];
    }

    private function facets($products, ?Category $category): array
    {
        $sizes = [];
        $colors = [];
        $diets = [];
        foreach ($products as $p) {
            foreach ($p->dietary_tags ?? [] as $tag) {
                $diets[$tag] = ($diets[$tag] ?? 0) + 1;
            }
            foreach ($p->sizes ?? [] as $s) {
                if (str_starts_with($s, '₹')) {
                    continue; // gift-card denominations are not garment sizes
                }
                $sizes[$s] = ($sizes[$s] ?? 0) + 1;
            }
            foreach ($p->colors ?? [] as $c) {
                $colors[$c['name']] = ['hex' => $c['hex'], 'count' => ($colors[$c['name']]['count'] ?? 0) + 1];
            }
        }
        uksort($sizes, fn ($a, $b) => $this->sizeRank($a) <=> $this->sizeRank($b));

        $categoryIds = $products->pluck('category_id')->unique();
        $subcategories = $category
            ? $category->children()->active()->whereIn('id', $categoryIds)->get()
            : collect();
        $categories = $category
            ? collect()
            : Category::active()->topLevel()->where('show_in_menu', true)->whereIn('id', $categoryIds)->get();

        return [
            'categories' => $categories,
            'subcategories' => $subcategories,
            'collections' => Collection::active()->whereHas('products', fn ($q) => $q->whereIn('products.id', $products->pluck('id')))->get(['id', 'name', 'slug']),
            'brands' => $products->pluck('brand')->filter()->countBy()->sortKeys(),
            'materials' => $products->pluck('material')->filter()->countBy()->sortKeys(),
            'sizes' => $sizes,
            'colors' => $colors,
            'diets' => collect($diets)->sortKeys(),
            'price_min' => (int) ($products->min('price') ?? 0),
            'price_max' => (int) ($products->max('price') ?? 0),
        ];
    }

    private function sizeRank(string $size): int
    {
        $order = ['XXS' => 0, 'XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4, 'XL' => 5, 'XXL' => 6, 'One size' => 100];
        if (isset($order[$size])) {
            return $order[$size];
        }
        if (is_numeric($size)) {
            return 200 + (int) $size;
        }
        if (preg_match('/^(\d+)ml$/i', $size, $m)) {
            return 300 + (int) $m[1];
        }

        return 400;
    }
}
