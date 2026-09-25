<?php

namespace App\Templates\Grocery;

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Supplies the grocery homepage with its section data (deals, fresh produce,
 * brands, section order) on top of what the shared HomeController provides.
 */
class HomeComposer
{
    public function compose(View $view): void
    {
        $home = tsetting('home');
        $limit = fn (string $key, int $default) => max(4, (int) ($home[$key] ?? $default));

        $deals = Product::with('category')->active()->onSale()
            ->orderByRaw('(compare_at_price - price) * 1.0 / nullif(compare_at_price, 0) desc')
            ->limit($limit('deals_limit', 10))->get();

        $fresh = collect();
        if ($slug = $home['fresh_category_slug'] ?? null) {
            if ($category = Category::active()->where('slug', $slug)->first()) {
                $ids = [$category->id, ...$category->children()->pluck('id')->all()];
                $fresh = Product::with('category')->active()->whereIn('category_id', $ids)
                    ->orderBy('sort_order')->limit($limit('fresh_limit', 10))->get();
            }
        }

        $brands = Product::active()->whereNotNull('brand')->where('brand', '!=', '')
            ->selectRaw('brand, count(*) as products')->groupBy('brand')
            ->orderByDesc('products')->limit(12)->get();

        $sections = collect($home['sections'] ?? [])
            ->filter(fn ($s) => ! empty($s['enabled']) && ! empty($s['key']))
            ->pluck('key')->values();

        $view->with([
            'g' => $home,
            'sections' => $sections,
            'deals' => $deals,
            'fresh' => $fresh,
            'brands' => $brands,
            'bestSellers' => Product::with('category')->active()->bestSellers()->orderBy('sort_order')->limit($limit('bestsellers_limit', 10))->get(),
            'newArrivals' => Product::with('category')->active()->newArrivals()->orderBy('sort_order')->limit($limit('new_limit', 10))->get(),
            'categoriesLimit' => $limit('categories_limit', 12),
        ]);
    }
}
