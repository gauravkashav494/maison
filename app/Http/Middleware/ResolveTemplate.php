<?php

namespace App\Http\Middleware;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Clients\Scopes\ClientVisibility;
use App\Contracts\AdminIdentity;
use App\Models\Category;
use App\Models\Client;
use App\Models\Collection;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\Faq;
use App\Models\Order;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\ServiceRequest;
use App\Models\Subscriber;
use App\Models\Storefront;
use App\Models\Testimonial;
use App\Templates\Scopes\TemplateVisibility;
use App\Templates\TemplateManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selects the client and the template for a storefront request.
 *
 * The client comes from the host (<slug>.<base domain> or a custom domain), or from the
 * entry-link cookie set by /c/<slug> when there is no DNS for subdomains. That client
 * decides three things: which database the content models read, the `client_id` scope on
 * every query, and the template whose views are used. A signed-in shopper belonging to a
 * different client is signed out rather than shown this client's pages — a session cannot
 * follow a customer from one client's site to another's.
 *
 * Admin and Livewire requests are left untouched; they are scoped by ApplyAdminScope.
 */
class ResolveTemplate
{
    /** Route names that only make sense with a product catalogue. */
    public const CATALOGUE_ROUTES = ['shop.*', 'collections.*', 'products.*', 'search', 'cart', 'cart.*', 'checkout', 'checkout.*', 'orders.*', 'track', 'api.search', 'api.products', 'api.product', 'account.orders', 'account.order', 'account.wishlist'];

    /** Catalogue and content models: limited to the template AND to the client being served. */
    public const SCOPED = [Product::class, Category::class, Collection::class, Page::class, Post::class, Faq::class, Service::class, ServiceArea::class, Testimonial::class, Project::class];

    /**
     * Models that belong to a client but are not catalogue content. They are scoped by client
     * only, so an order number, coupon code or booking reference from one client's site cannot
     * be looked up on another's.
     */
    public const CLIENT_SCOPED = [Order::class, Coupon::class, Review::class, ServiceRequest::class, ContactMessage::class, Subscriber::class];

    public function handle(Request $request, Closure $next): Response
    {
        // Admin panels, Livewire (update/upload endpoints use a hashed prefix) and health checks
        // are not storefront requests.
        if ($request->is('admin', 'admin/*', 'client', 'client/*', 'livewire*', 'up') || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        $manager = app(TemplateManager::class);

        // ?preview_template=grocery (admins only) previews a template for this session; blank exits.
        if ($request->query->has('preview_template')) {
            return $this->previewTemplate($request, $manager);
        }

        // Each client is public on its own host (<slug>.<base domain> or a custom domain); the
        // main domain serves the template activated under Appearance → Templates.
        $client = $this->clientForRequest($request);
        $publicId = $client?->templateId()
            ?? Storefront::templateForHost($request->getHost())
            ?? Storefront::templateForCookie($request->cookie(Storefront::COOKIE))
            ?? $manager->activeId();
        if ($publicId !== $manager->activeId() && ! $manager->previewId()) {
            $manager->setCurrent($manager->get($publicId));
        }

        // Signed-in staff see the store they manage instead: a client's staff always, super
        // admins when they narrowed the admin to one client (an explicit preview wins).
        $this->followAdminStore($request, $manager);

        $template = $manager->current();

        // A previewing admin looks at their own client's data, not at the host's.
        $client = $this->adminClient() ?? $client ?? $this->defaultClientFor($template->id());

        app(ClientContext::class)->resolveForPublic($client);
        app(ClientDatabase::class)->use($client);
        $this->guardCustomerSession($client);

        $template->boot();

        // Service-business templates have no catalogue: shop, cart, checkout and order routes answer 404.
        if ($template->supportsServices() && $request->route()?->named(self::CATALOGUE_ROUTES)) {
            abort(404);
        }

        if ($path = $template->viewPath()) {
            View::getFinder()->prependLocation(resource_path('views/'.$path));
        }

        foreach (self::SCOPED as $model) {
            $model::addGlobalScope(TemplateVisibility::NAME, new TemplateVisibility($template->id()));
        }
        if ($client) {
            foreach ([...self::SCOPED, ...self::CLIENT_SCOPED] as $model) {
                $model::addGlobalScope(ClientVisibility::NAME, new ClientVisibility($client->id));
            }
        }

        $response = $next($request);

        if ($template->id() !== $publicId && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $this->injectPreviewBar($response, $template->name(), $manager->get($publicId)->name(), ! $this->adminIdentity($request)?->isSuperAdmin());
        }

        return $response;
    }

    /** The client this host (or entry-link cookie) belongs to. Never taken from a parameter. */
    private function clientForRequest(Request $request): ?Client
    {
        // Host first (a subdomain or a custom domain), then the entry link, which is kept in
        // the session as well as a cookie so it survives a browser that drops cookies.
        $id = Client::idForHost($request->getHost())
            ?? Client::idForSlug($request->cookie(Client::COOKIE))
            ?? Client::idForSlug($request->hasSession() ? $request->session()->get(Client::SESSION_KEY) : null);

        return $id ? Client::active()->find($id) : null;
    }

    /** First client running a template — what the main domain shows. */
    private function defaultClientFor(?string $template): ?Client
    {
        if (! $template) {
            return null;
        }

        return Client::active()
            ->whereHas('storefront', fn ($query) => $query->where('template', $template))
            ->orderBy('id')
            ->first();
    }

    /** Client the signed-in admin session is working in, if any. */
    private function adminClient(): ?Client
    {
        $client = app(ClientContext::class)->client();

        return $client && app(ClientContext::class)->source() !== 'host' ? $client : null;
    }

    /**
     * A shopper's session belongs to one client. If the request is for another client (a
     * different host, or an entry link followed in the same browser), the session is dropped
     * for this request instead of being allowed to carry data across.
     */
    private function guardCustomerSession(?Client $client): void
    {
        $customer = Auth::guard('web')->user();
        if (! $customer) {
            return;
        }
        if (! $client || (int) $customer->client_id !== (int) $client->id || ! $customer->is_active) {
            Auth::guard('web')->logout();
        }
    }

    /** The staff account signed into either admin panel, read from that guard only. */
    private function adminIdentity(Request $request): ?AdminIdentity
    {
        $user = Auth::guard('admin')->user() ?? Auth::guard('client')->user();

        return $user instanceof AdminIdentity && $user->isActiveAccount() ? $user : null;
    }

    private function previewTemplate(Request $request, TemplateManager $manager): Response
    {
        $id = (string) $request->query('preview_template');
        $user = $this->adminIdentity($request);
        $mayPreview = $user && ($user->isSuperAdmin() || $id === '' || $id === $user->managedTemplateId());
        if ($mayPreview) {
            $id !== '' && $manager->has($id)
                ? $request->session()->put(TemplateManager::PREVIEW_SESSION, $id)
                : $request->session()->forget(TemplateManager::PREVIEW_SESSION);
        }

        return redirect()->to($request->fullUrlWithoutQuery('preview_template'));
    }

    private function followAdminStore(Request $request, TemplateManager $manager): void
    {
        $user = $this->adminIdentity($request);
        if (! $user || ! $request->hasSession()) {
            return;
        }
        if (! $user->isSuperAdmin()) {
            $own = $user->managedTemplateId();
            if ($own && $manager->has($own)) {
                $request->session()->put(TemplateManager::PREVIEW_SESSION, $own);
            }

            return;
        }
        // Super admin: no explicit preview → mirror the admin client switcher.
        if (! $request->session()->has(TemplateManager::PREVIEW_SESSION)) {
            $chosen = app(ClientContext::class)->templateId() ?? $request->session()->get(StoreContext::SESSION_KEY);
            if ($chosen && $manager->has($chosen)) {
                $manager->setCurrent($manager->get($chosen));
            }
        }
    }

    private function injectPreviewBar(Response $response, string $previewing, string $active, bool $locked = false): void
    {
        $content = $response->getContent();
        if (! is_string($content) || ! str_contains($content, '</body>')) {
            return;
        }
        $exit = e(url()->current().'?preview_template=');
        $bar = '<div style="position:fixed;left:0;right:0;bottom:0;z-index:2147483647;display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:center;justify-content:center;padding:.6rem 1rem;background:#111;color:#fff;font:500 13px/1.4 system-ui,sans-serif;box-shadow:0 -4px 20px rgba(0,0,0,.25)">'
            .($locked
                ? '<span>You are viewing your store, <strong>'.e($previewing).'</strong> — visitors here see <strong>'.e($active).'</strong>. Share your own store link from the admin dashboard.</span>'
                : '<span>Previewing the <strong>'.e($previewing).'</strong> template — visitors still see <strong>'.e($active).'</strong>.</span>'
                    .'<a href="'.$exit.'" style="color:#fff;text-decoration:underline">Exit preview</a>')
            .'<a href="'.e(url($locked ? '/client' : '/admin/templates')).'" style="color:#fff;text-decoration:underline">'.($locked ? 'Back to admin' : 'Manage templates').'</a>'
            .'</div>';
        $response->setContent(str_replace('</body>', $bar.'</body>', $content));
    }
}
