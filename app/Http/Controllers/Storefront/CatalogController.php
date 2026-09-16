<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\Order;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    // ---- Collections -------------------------------------------------------

    public function collections(): View
    {
        return view('shop.collections', [
            'collections' => Collection::active()->withCount('products')->get(),
            'seo' => Seo::simple('Collections', 'Editorial edits for every season and occasion.'),
        ]);
    }

    public function collection(string $slug): View
    {
        $collection = Collection::active()->where('slug', $slug)->firstOrFail();
        $products = $collection->products()->with('category')->active()->get();

        return view('shop.collection', [
            'collection' => $collection,
            'products' => $products,
            'featured' => $products->take(3),
            'related' => Collection::active()->whereKeyNot($collection->id)->limit(3)->get(),
            'seo' => Seo::forModel($collection, $collection->url),
        ]);
    }

    // ---- Products ----------------------------------------------------------

    public function product(string $slug): View
    {
        $product = Product::with(['category.parent', 'collections'])->active()->where('slug', $slug)->firstOrFail();

        $related = Product::with('category')->active()
            ->where('category_id', $product->category_id)->whereKeyNot($product->id)
            ->orderBy('sort_order')->limit(4)->get();

        // "Complete the look": pieces from the same collections, other categories first.
        $completeTheLook = Product::with('category')->active()
            ->whereHas('collections', fn ($q) => $q->whereIn('collections.id', $product->collections->pluck('id')))
            ->whereKeyNot($product->id)
            ->orderByRaw('case when category_id = ? then 1 else 0 end', [$product->category_id])
            ->limit(4)->get();

        $reviews = $product->reviews()->approved()->limit(10)->get();
        $breakdown = $product->reviews()->approved()->selectRaw('rating, count(*) as c')->groupBy('rating')->pluck('c', 'rating');

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->description,
            'image' => $product->image_urls,
            'sku' => $product->sku,
            'brand' => ['@type' => 'Brand', 'name' => $product->brand ?: setting('site.name')],
            'offers' => [
                '@type' => 'Offer',
                'url' => $product->url,
                'priceCurrency' => 'INR',
                'price' => $product->price,
                'availability' => $product->in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
        ];
        if ($product->review_count > 0) {
            $jsonLd['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $product->rating, 'reviewCount' => $product->review_count];
        }

        return view('shop.product', [
            'product' => $product,
            'related' => $related,
            'completeTheLook' => $completeTheLook,
            'reviews' => $reviews,
            'breakdown' => $breakdown,
            'seo' => Seo::forModel($product, $product->url, 'product', [$jsonLd]),
        ]);
    }

    public function storeReview(Request $request, string $slug): RedirectResponse
    {
        $product = Product::active()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:190'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $product->reviews()->create($data + ['user_id' => $request->user()?->id, 'is_approved' => false]);

        return back()->with('review_status', 'Thank you — your review has been submitted and will appear once approved.')->withFragment('reviews');
    }

    public function productJson(string $slug): JsonResponse
    {
        return response()->json(Product::with('category')->active()->where('slug', $slug)->firstOrFail()->toCard());
    }

    /** Batch lookup used by "recently viewed" and the wishlist page (client-side lists of slugs/ids). */
    public function productsJson(Request $request): JsonResponse
    {
        $slugs = array_filter(explode(',', (string) $request->query('slugs', '')));
        $ids = array_filter(explode(',', (string) $request->query('ids', '')));
        $query = Product::with('category')->active();
        if ($slugs) {
            $query->whereIn('slug', $slugs);
        } elseif ($ids) {
            $query->whereIn('id', $ids);
        } else {
            return response()->json([]);
        }
        $items = $query->get()->map(fn (Product $p) => $p->toCard());
        // Preserve the caller's order
        $order = $slugs ? array_flip(array_values($slugs)) : array_flip(array_map('intval', array_values($ids)));
        $items = $items->sortBy(fn ($c) => $order[$slugs ? $c['slug'] : $c['id']] ?? 999)->values();

        return response()->json($items);
    }

    // ---- Search ------------------------------------------------------------

    public function search(Request $request): View
    {
        $q = trim($request->string('q')->toString());
        $products = $q === '' ? collect() : $this->searchQuery($q)->paginate(24)->withQueryString();

        return view('shop.search', [
            'q' => $q,
            'products' => $products,
            'seo' => Seo::simple($q ? "Search: {$q}" : 'Search', null, noindex: true),
        ]);
    }

    public function searchJson(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());
        if ($q === '') {
            return response()->json(['products' => [], 'categories' => []]);
        }

        return response()->json([
            'products' => $this->searchQuery($q)->limit(6)->get()->map(fn (Product $p) => $p->toCard())->values(),
            'categories' => Category::active()->where('name', 'like', "%{$q}%")->limit(4)->get()->map(fn ($c) => ['name' => $c->name, 'url' => $c->url])->values(),
        ]);
    }

    private function searchQuery(string $q)
    {
        return Product::with('category')->active()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhere('brand', 'like', "%{$q}%")
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$q}%")))
            ->orderBy('sort_order');
    }

    // ---- CMS pages (template-driven) --------------------------------------

    public function page(string $slug): View
    {
        $page = Page::active()->where('slug', $slug)->firstOrFail();
        $template = in_array($page->template, array_keys(Page::TEMPLATES), true) ? $page->template : 'default';

        $extra = match ($template) {
            'faq' => ['faqs' => Faq::active()->get()->groupBy('category')->sortBy(fn ($g, $k) => array_search($k, Faq::CATEGORIES) ?? 99)],
            'stores' => ['stores' => Store::active()->get()],
            'contact' => ['faqs' => Faq::active()->limit(4)->get()],
            'gift-cards' => ['giftCard' => Product::active()->where('slug', 'gift-card')->first()],
            default => [],
        };

        return view("pages.templates.{$template}", ['page' => $page, 'seo' => Seo::forModel($page, $page->url, 'article')] + $extra);
    }

    public function contactStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:3000'],
            'website' => ['prohibited'], // honeypot
        ]);
        unset($data['website']);
        ContactMessage::create($data);

        return back()->with('contact_status', 'Thank you. Our client care team will reply within one business day.');
    }

    // ---- Journal -----------------------------------------------------------

    public function journal(): View
    {
        return view('pages.journal', [
            'posts' => Post::published()->paginate(12),
            'seo' => Seo::simple('The Journal', 'Notes on style, craft and the season.'),
        ]);
    }

    public function post(string $slug): View
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();

        return view('pages.post', [
            'post' => $post,
            'more' => Post::published()->whereKeyNot($post->id)->limit(2)->get(),
            'seo' => Seo::forModel($post, $post->url, 'article'),
        ]);
    }

    // ---- Order tracking ----------------------------------------------------

    public function track(Request $request): View
    {
        $order = null;
        $error = null;
        if ($request->filled('number')) {
            $request->validate(['number' => ['required', 'string', 'max:40'], 'email' => ['required', 'email']]);
            $order = Order::with('items')->where('number', strtoupper(trim($request->number)))
                ->whereRaw('lower(email) = ?', [strtolower(trim($request->email))])->first();
            $error = $order ? null : 'We could not find an order with that number and email.';
        }

        return view('shop.track', [
            'order' => $order,
            'error' => $error,
            'seo' => Seo::simple('Track your order', 'Enter your order number and email to see delivery progress.', noindex: true),
        ]);
    }
}
