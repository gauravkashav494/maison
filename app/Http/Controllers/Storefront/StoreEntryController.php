<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entry links for hosts without wildcard DNS.
 *
 *   /c/<client-slug>     pins a client's storefront in a cookie and opens it on this domain
 *   /store/<store-slug>  the older store-level link, kept working for existing links
 *
 * The cookie only chooses which public storefront is shown; it grants nothing. Any shopper
 * session is ended as the client changes, so one client's account never follows the visitor
 * into another client's site (ResolveTemplate checks this on every request as well).
 */
class StoreEntryController extends Controller
{
    public function enterClient(Request $request, string $slug): RedirectResponse
    {
        $client = Client::active()->where('slug', $slug)->firstOrFail();
        $this->endShopperSession($request);
        $request->session()->put(Client::SESSION_KEY, $client->slug);

        return redirect()->to($this->safePath($request))
            ->withCookie(cookie(Client::COOKIE, $client->slug, 60 * 24 * 365))
            ->withCookie(cookie()->forget(Storefront::COOKIE));
    }

    /**
     * A standing link straight to the Simple List template's bulk order page. It pins the
     * cookie exactly as /c/<slug> does — so the cart, checkout and order confirmation that
     * follow all resolve to the same client — but looks the client up by template instead of
     * a fixed slug, and always overrides whatever store the cookie previously pointed to, so
     * opening this link never requires visiting /store/exit first.
     */
    public function bulkOrder(Request $request): RedirectResponse
    {
        $client = Client::active()
            ->whereHas('storefront', fn ($query) => $query->where('template', 'simple'))
            ->orderBy('id')
            ->firstOrFail();
        $this->endShopperSession($request);
        $request->session()->put(Client::SESSION_KEY, $client->slug);

        return redirect()->to('/shop')
            ->withCookie(cookie(Client::COOKIE, $client->slug, 60 * 24 * 365))
            ->withCookie(cookie()->forget(Storefront::COOKIE));
    }

    public function enter(Request $request, string $slug): RedirectResponse
    {
        $store = Storefront::active()->where('slug', $slug)->firstOrFail();
        $this->endShopperSession($request);

        // Prefer the client running this store, so the visitor lands on real client data.
        $client = Client::active()->where('storefront_id', $store->id)->orderBy('id')->first();
        $client
            ? $request->session()->put(Client::SESSION_KEY, $client->slug)
            : $request->session()->forget(Client::SESSION_KEY);

        $redirect = redirect()->to($this->safePath($request))
            ->withCookie(cookie(Storefront::COOKIE, $store->slug, 60 * 24 * 365));

        return $client
            ? $redirect->withCookie(cookie(Client::COOKIE, $client->slug, 60 * 24 * 365))
            : $redirect->withCookie(cookie()->forget(Client::COOKIE));
    }

    public function exit(Request $request): RedirectResponse
    {
        $this->endShopperSession($request);
        $request->session()->forget(Client::SESSION_KEY);

        return redirect()->to('/')
            ->withCookie(cookie()->forget(Storefront::COOKIE))
            ->withCookie(cookie()->forget(Client::COOKIE));
    }

    /** Only same-site paths are followed, never an absolute or protocol-relative URL. */
    private function safePath(Request $request): string
    {
        $to = (string) $request->query('to', '/');

        return str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/';
    }

    private function endShopperSession(Request $request): void
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->regenerate();
        }
    }
}
