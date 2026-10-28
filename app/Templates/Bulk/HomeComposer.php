<?php

namespace App\Templates\Bulk;

use App\Http\Controllers\Storefront\BulkOrderController;
use App\Models\Faq;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Supplies the bulk-order homepage: the products the order form suggests before anything
 * is typed, the section data (featured, deals, brands, rails, FAQs) and the ordered list of
 * enabled sections.
 */
class HomeComposer
{
    public function compose(View $view): void
    {
        $home = tsetting('home');
        $limit = fn (string $key, int $default) => max(4, (int) ($home[$key] ?? $default));

        // "Most ordered" = best sellers first, then highest rated, so the rail is never empty.
        $featured = Product::with('category')->active()
            ->orderByDesc('is_best_seller')->orderByDesc('rating')->orderByDesc('review_count')
            ->limit($limit('featured_limit', 10))->get();

        $sections = collect($home['sections'] ?? [])
            ->filter(fn ($s) => ! empty($s['enabled']) && ! empty($s['key']))
            ->pluck('key')->values();

        $view->with([
            'g' => $home,
            'sections' => $sections,
            'suggestions' => BulkOrderController::suggestions(),
            'featured' => $featured,
            'deals' => $sections->contains('deals') ? Product::with('category')->active()->onSale()
                ->orderByRaw('(compare_at_price - price) * 1.0 / nullif(compare_at_price, 0) desc')
                ->limit($limit('deals_limit', 8))->get() : collect(),
            'brands' => $sections->contains('brands') ? Product::active()->whereNotNull('brand')->where('brand', '!=', '')
                ->selectRaw('brand, count(*) as products')->groupBy('brand')
                ->orderByDesc('products')->limit(12)->get() : collect(),
            'bestSellers' => $sections->contains('bestsellers') ? Product::with('category')->active()->bestSellers()->orderBy('sort_order')->limit($limit('bestsellers_limit', 10))->get() : collect(),
            'newArrivals' => $sections->contains('new') ? Product::with('category')->active()->newArrivals()->orderBy('sort_order')->limit($limit('new_limit', 10))->get() : collect(),
            'faqs' => $sections->contains('faq') ? Faq::active()->orderBy('sort_order')->limit(6)->get() : collect(),
            'categoriesLimit' => $limit('categories_limit', 9),
        ]);
    }
}
