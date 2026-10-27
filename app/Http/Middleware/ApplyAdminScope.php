<?php

namespace App\Http\Middleware;

use App\Admin\AdminStoreScope;
use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Clients\Scopes\ClientOwned;
use App\Contracts\AdminIdentity;
use App\Models\Client;
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
 * A SuperAdmin may switch client with ?switch_client=<id>, which is validated against the
 * clients table before it is stored in the session.
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

        if ($identity?->isSuperAdmin() && $request->query->has('switch_client')) {
            $chosen = (string) $request->query('switch_client');
            $client = $chosen !== '' && $chosen !== 'all' ? Client::find($chosen) : null;
            $client
                ? $request->session()->put(ClientContext::SESSION_KEY, $client->id)
                : $request->session()->forget(ClientContext::SESSION_KEY);

            return redirect()->to($request->fullUrlWithoutQuery('switch_client'));
        }

        // Only a SuperAdmin's choice is read from the session; staff accounts ignore it entirely.
        $chosen = $identity?->isSuperAdmin() && $request->hasSession()
            ? $request->session()->get(ClientContext::SESSION_KEY)
            : null;

        $this->clients->resolveForIdentity($identity, $chosen);

        // A per-client database cannot answer a query across clients, so one is always selected.
        if ($identity?->isSuperAdmin() && ! $this->clients->id() && $this->databases->isolated()) {
            $first = Client::active()->orderBy('name')->value('id');
            $first && $this->clients->resolveForIdentity($identity, $first);
        }

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
