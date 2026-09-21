<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Testimonial;
use App\Templates\Scopes\TemplateVisibility;
use App\Templates\TemplateManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selects the storefront template for this request. Prepends the template's view
 * folder so `view('shop.product')` resolves to the template's copy (falling back to
 * the base views) and limits the catalogue to rows visible in that template.
 * Admin and Livewire requests are left untouched.
 */
class ResolveTemplate
{
    /** Route names that only make sense with a product catalogue. */
    public const CATALOGUE_ROUTES = ['shop.*', 'collections.*', 'products.*', 'search', 'cart', 'cart.*', 'checkout', 'checkout.*', 'orders.*', 'track', 'api.search', 'api.products', 'api.product', 'account.orders', 'account.order', 'account.wishlist'];

    public function handle(Request $request, Closure $next): Response
    {
        // Admin panel, Livewire (update/upload endpoints use a hashed prefix) and health checks are not storefront requests.
        if ($request->is('admin', 'admin/*', 'livewire*', 'up') || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        $manager = app(TemplateManager::class);

        // ?preview_template=grocery (admins only) previews a template for this session; blank exits.
        if ($request->query->has('preview_template')) {
            $id = (string) $request->query('preview_template');
            if ($request->user()?->is_admin) {
                $id !== '' && $manager->has($id)
                    ? $request->session()->put(TemplateManager::PREVIEW_SESSION, $id)
                    : $request->session()->forget(TemplateManager::PREVIEW_SESSION);
            }

            return redirect()->to($request->fullUrlWithoutQuery('preview_template'));
        }

        $template = $manager->current();
        $template->boot();

        // Service-business templates have no catalogue: shop, cart, checkout and order routes answer 404.
        if ($template->supportsServices() && $request->route()?->named(self::CATALOGUE_ROUTES)) {
            abort(404);
        }

        if ($path = $template->viewPath()) {
            View::getFinder()->prependLocation(resource_path('views/'.$path));
        }

        foreach ([Product::class, Category::class, Collection::class, Page::class, Post::class, Faq::class, Service::class, ServiceArea::class, Testimonial::class, Project::class] as $model) {
            $model::addGlobalScope(TemplateVisibility::NAME, new TemplateVisibility($template->id()));
        }

        $response = $next($request);

        if ($manager->isPreviewing() && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $this->injectPreviewBar($response, $template->name(), $manager->active()->name());
        }

        return $response;
    }

    private function injectPreviewBar(Response $response, string $previewing, string $active): void
    {
        $content = $response->getContent();
        if (! is_string($content) || ! str_contains($content, '</body>')) {
            return;
        }
        $exit = e(url()->current().'?preview_template=');
        $bar = '<div style="position:fixed;left:0;right:0;bottom:0;z-index:2147483647;display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:center;justify-content:center;padding:.6rem 1rem;background:#111;color:#fff;font:500 13px/1.4 system-ui,sans-serif;box-shadow:0 -4px 20px rgba(0,0,0,.25)">'
            .'<span>Previewing the <strong>'.e($previewing).'</strong> template — visitors still see <strong>'.e($active).'</strong>.</span>'
            .'<a href="'.$exit.'" style="color:#fff;text-decoration:underline">Exit preview</a>'
            .'<a href="'.e(url('/admin/templates')).'" style="color:#fff;text-decoration:underline">Manage templates</a>'
            .'</div>';
        $response->setContent(str_replace('</body>', $bar.'</body>', $content));
    }
}
