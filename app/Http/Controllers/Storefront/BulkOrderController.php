<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Seo;
use Illuminate\View\View;

/**
 * A single page for adding many products to the cart at once: search, pick a line,
 * set a quantity, repeat. It hands finished lines to the existing cart (the same
 * `/cart/items` endpoint every product card already uses), so the cart, checkout and
 * order confirmation it leads to are unchanged.
 *
 * Every store serves it on its own URL (<store host>/bulk-order) and only ever lists its
 * own products: ResolveTemplate scopes the catalogue to the store being served.
 * Service-business templates have no catalogue, so it 404s there (CATALOGUE_ROUTES).
 */
class BulkOrderController extends Controller
{
    public function index(): View
    {
        return view('shop.bulk-order', [
            'seo' => Seo::simple('Bulk Order', 'Search for products, set quantities and add them all to your cart at once.', noindex: true),
            'suggestions' => static::suggestions(),
        ]);
    }

    /**
     * Products shown the moment a search box is clicked, before anything is typed: best
     * sellers first, topped up with other products so a new store's list is never empty.
     */
    public static function suggestions(): \Illuminate\Support\Collection
    {
        return Product::active()->with('category')->orderByDesc('is_best_seller')->orderBy('sort_order')
            ->limit(8)->get()->map->toCard()->values();
    }
}
