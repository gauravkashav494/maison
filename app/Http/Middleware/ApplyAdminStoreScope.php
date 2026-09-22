<?php

namespace App\Http\Middleware;

use App\Admin\AdminStoreScope;
use App\Admin\StoreContext;
use App\Models\Storefront;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the admin store context for the signed-in staff member and applies the
 * AdminStoreScope global scope to every template-owned model. Registered as a panel
 * middleware AND as a persistent middleware so Livewire updates (tables, forms, actions)
 * are scoped exactly like page loads.
 *
 * Super admins may switch store with ?switch_store=<slug|all>; the choice lives in the
 * session. Store owners are locked to the store on their account — nothing in the request
 * can change it.
 */
class ApplyAdminStoreScope
{
    public function __construct(private readonly StoreContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isSuperAdmin() && $request->query->has('switch_store')) {
            $slug = (string) $request->query('switch_store');
            $store = $slug !== '' && $slug !== 'all' ? Storefront::where('slug', $slug)->first() : null;
            $store ? $request->session()->put(StoreContext::SESSION_KEY, $store->template) : $request->session()->forget(StoreContext::SESSION_KEY);

            return redirect()->to($request->fullUrlWithoutQuery('switch_store'));
        }

        $this->context->resolve($user, $user?->isSuperAdmin() ? $request->session()->get(StoreContext::SESSION_KEY) : null);

        if ($template = $this->context->template()) {
            foreach (StoreContext::SCOPED_MODELS as $model) {
                $model::addGlobalScope(AdminStoreScope::NAME, new AdminStoreScope($template));
            }
        }

        return $next($request);
    }
}
