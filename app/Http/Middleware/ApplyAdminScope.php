<?php

namespace App\Http\Middleware;

use App\Admin\AdminStoreScope;
use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Clients\Scopes\ClientOwned;
use App\Contracts\AdminIdentity;
use App\Models\Client;
use App\Models\ClientUser;
use App\Support\PanelSignOut;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves who is signed into an admin panel and locks the request to one client.
 *
 * Registered as a panel middleware AND as a persistent middleware on both panels, so
 * Livewire updates (tables, forms, actions, uploads) are scoped exactly like page loads —
 * the common way tenancy leaks is a scope applied on the page load but not on the update.
 *
 * Three layers are applied together, and each one alone would be enough to make another
 * client's row invisible:
 *
 *   1. the client's database is selected (when isolation is on);
 *   2. a `client_id` global scope is added to every content model;
 *   3. the template scope and the policies keep working on top.
 *
 * A client's staff cannot influence any of this: their client comes from their account row.
 */
class ApplyAdminScope
{
    public function __construct(
        private readonly StoreContext $stores,
        private readonly ClientContext $clients,
        private readonly ClientDatabase $databases,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $this->identity($request);

        // A store account is only valid on its own store's host. A session that reaches another
        // store's subdomain (shared session cookie, or one signed in before host-bound logins)
        // is signed out rather than shown its own store's data under someone else's address.
        if ($identity instanceof ClientUser && ($hostClient = Client::idForHost($request->getHost()))
            && (int) $identity->client_id !== $hostClient) {
            PanelSignOut::guard($request, 'client');

            return redirect()->to(Filament::getPanel('client')->getLoginUrl());
        }

        // A SuperAdmin works in no client at all; a client's staff are bound to their own.
        $this->clients->resolveForIdentity($identity);

        $this->databases->use($this->clients->client());
        $this->stores->resolve($identity, $this->clients->templateId());

        if ($clientId = $this->clients->id()) {
            foreach (StoreContext::SCOPED_MODELS as $model) {
                $model::addGlobalScope(ClientOwned::NAME, new ClientOwned($clientId));
            }
        }

        if ($template = $this->stores->template()) {
            foreach (StoreContext::SCOPED_MODELS as $model) {
                $model::addGlobalScope(AdminStoreScope::NAME, new AdminStoreScope($template));
            }
        }

        return $next($request);
    }

    /**
     * The account signed into the panel being served — taken from that panel's own guard, so
     * a storefront (customer) session can never be read as an admin, and a client staff
     * session can never be read as a SuperAdmin.
     */
    private function identity(Request $request): ?AdminIdentity
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $user = $panel ? $panel->auth()->user() : null;

        if (! $user) {
            // Outside a panel request (queued Livewire endpoints, console) fall back to the
            // admin guards only; never to the storefront guard.
            $user = auth('admin')->user() ?? auth('client')->user();
        }

        return $user instanceof AdminIdentity ? $user : null;
    }
}
