<?php

namespace App\Templates\Plumbing;

use App\Models\Faq;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Supplies the plumbing homepage with its section data (deals, featured, brands,
 * bestsellers, new arrivals, FAQs) and the ordered list of enabled sections.
 */
class HomeComposer
{
    public function compose(View $view): void
    {
        $home = tsetting('home');
        $limit = fn (string $key, int $default) => max(4, (int) ($home[$key] ?? $default));

        $deals = Product::with('category')->active()->onSale()
            ->orderByRaw('(compare_at_price - price) * 1.0 / compare_at_price desc')
            ->limit($limit('deals_limit', 8))->get();

        // "Featured" = best sellers first, then highest rated, so the rail is never empty.
        $featured = Product::with('category')->active()
            ->orderByDesc('is_best_seller')->orderByDesc('rating')->orderByDesc('review_count')
            ->limit($limit('featured_limit', 10))->get();

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
            'featured' => $featured,
            'brands' => $brands,
            'bestSellers' => Product::with('category')->active()->bestSellers()->orderBy('sort_order')->limit($limit('bestsellers_limit', 10))->get(),
            'newArrivals' => Product::with('category')->active()->newArrivals()->orderBy('sort_order')->limit($limit('new_limit', 10))->get(),
            'faqs' => $sections->contains('faq') ? Faq::active()->orderBy('sort_order')->limit(6)->get() : collect(),
            'categoriesLimit' => $limit('categories_limit', 9),
        ]);
    }
}
