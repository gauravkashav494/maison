<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Post;
use App\Models\Product;
use App\Support\Seo;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $home = setting('home', []);

        $categories = Category::active()->topLevel()->where('show_in_menu', true)->get();

        $newArrivals = Product::with('category')->active()->newArrivals()
            ->orderBy('sort_order')->limit((int) ($home['arrivals_limit'] ?? 8))->get();

        $bestSellers = Product::with('category')->active()->bestSellers()->orderBy('sort_order')->limit(10)->get();

        $featured = ! empty($home['featured_collection_id'])
            ? Collection::active()->find($home['featured_collection_id'])
            : Collection::active()->where('is_featured', true)->first();

        $fragrance = ! empty($home['fragrance_product_id'])
            ? Product::with('category')->active()->find($home['fragrance_product_id'])
            : null;

        $posts = Post::published()->orderByDesc('is_featured')->orderByDesc('published_at')->limit(3)->get();

        $collectionIndex = $featured
            ? Collection::active()->pluck('id')->search($featured->id) + 1
            : null;

        return view('home.index', [
            'home' => $home,
            'categories' => $categories,
            'newArrivals' => $newArrivals,
            'bestSellers' => $bestSellers,
            'featured' => $featured,
            'collectionIndex' => $collectionIndex,
            'collectionTotal' => Collection::active()->count(),
            'fragrance' => $fragrance,
            'posts' => $posts,
            'seo' => Seo::forHome(),
        ]);
    }
}
