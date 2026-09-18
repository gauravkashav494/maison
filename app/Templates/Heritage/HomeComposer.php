<?php

namespace App\Templates\Heritage;

use App\Models\Collection;
use App\Models\Post;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Homepage data for the Heritage template on top of the shared HomeController:
 * featured/bestseller rails, "shop by need" collections, brands and section order.
 */
class HomeComposer
{
    public function compose(View $view): void
    {
        $home = tsetting('home');
        $limit = fn (string $key, int $default) => max(4, (int) ($home[$key] ?? $default));

        $featured = Product::with('category')->active();
        if (($home['featured_source'] ?? 'new') === 'collection' && ! empty($home['featured_collection_slug'])) {
            $featured->whereHas('collections', fn ($q) => $q->where('slug', $home['featured_collection_slug']));
        } else {
            $featured->newArrivals();
        }

        $brands = collect($home['brands'] ?? [])->filter(fn ($b) => ! empty($b['name']))->values();
        if ($brands->isEmpty()) {
            // Fall back to the brands found on the catalogue.
            $brands = Product::active()->whereNotNull('brand')->where('brand', '!=', '')
                ->selectRaw('brand, count(*) as products')->groupBy('brand')->orderByDesc('products')->limit(10)->get()
                ->map(fn ($b) => ['name' => $b->brand, 'url' => route('shop.index', ['brand' => $b->brand]), 'logo' => null, 'count' => $b->products]);
        }

        $view->with([
            'g' => $home,
            'sections' => collect($home['sections'] ?? [])->filter(fn ($s) => ! empty($s['enabled']) && ! empty($s['key']))->pluck('key')->values(),
            'featuredProducts' => $featured->orderBy('sort_order')->limit($limit('featured_limit', 8))->get(),
            'bestSellers' => Product::with('category')->active()->bestSellers()->orderBy('sort_order')->limit($limit('bestsellers_limit', 8))->get(),
            'needs' => Collection::active()->withCount('products')->orderBy('sort_order')->limit($limit('needs_limit', 6))->get(),
            'brands' => $brands,
            'posts' => Post::published()->orderByDesc('is_featured')->orderByDesc('published_at')->limit(3)->get(),
            'categoriesLimit' => $limit('categories_limit', 10),
        ]);
    }
}
