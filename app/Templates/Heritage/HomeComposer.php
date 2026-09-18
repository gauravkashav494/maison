<?php

namespace App\Templates\Heritage;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Post;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Homepage data for the Heritage template: product carousels (featured, combos,
 * bestsellers, new), tabbed "shop by need" and "range of categories" blocks,
 * journal posts and the section order.
 */
class HomeComposer
{
    public function compose(View $view): void
    {
        $home = tsetting('home');
        $limit = fn (string $key, int $default) => max(4, (int) ($home[$key] ?? $default));
        $base = fn () => Product::with('category')->active()->orderBy('sort_order');

        $featured = $base();
        if (($home['featured_source'] ?? 'collection') === 'collection' && ! empty($home['featured_collection_slug'])) {
            $featured->whereHas('collections', fn ($q) => $q->where('slug', $home['featured_collection_slug']));
        } else {
            $featured->newArrivals();
        }

        $combos = ! empty($home['combos_collection_slug'])
            ? $base()->whereHas('collections', fn ($q) => $q->where('slug', $home['combos_collection_slug']))->limit($limit('combos_limit', 10))->get()
            : collect();

        // Tabbed blocks: each tab carries its own product list (loaded once, switched client-side).
        $needs = Collection::active()->orderBy('sort_order')->limit($limit('needs_limit', 8))->get()
            ->map(fn (Collection $c) => ['model' => $c, 'products' => $c->products()->with('category')->active()->orderBy('collection_product.sort_order')->limit(8)->get()])
            ->filter(fn ($t) => $t['products']->isNotEmpty())->values();

        $categoryTabs = Category::active()->topLevel()->where('show_in_menu', true)->limit($limit('categories_limit', 10))->get()
            ->map(function (Category $c) use ($base) {
                $ids = [$c->id, ...$c->children()->pluck('id')->all()];

                return ['model' => $c, 'products' => $base()->whereIn('category_id', $ids)->limit(8)->get()];
            })->filter(fn ($t) => $t['products']->isNotEmpty())->values();

        $view->with([
            'g' => $home,
            'sections' => collect($home['sections'] ?? [])->filter(fn ($s) => ! empty($s['enabled']) && ! empty($s['key']))->pluck('key')->values(),
            'featuredProducts' => $featured->limit($limit('featured_limit', 10))->get(),
            'combos' => $combos,
            'bestSellers' => $base()->bestSellers()->limit($limit('bestsellers_limit', 10))->get(),
            'newArrivals' => $base()->newArrivals()->limit($limit('new_limit', 10))->get(),
            'needs' => $needs,
            'categoryTabs' => $categoryTabs,
            'posts' => Post::published()->orderByDesc('is_featured')->orderByDesc('published_at')->limit(5)->get(),
        ]);
    }
}
