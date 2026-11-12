<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Old entry links on the main domain. A client is only ever reached on its own subdomain (or
 * custom domain), so every link that names a client redirects there:
 *
 *   /store/<subdomain>    redirects to <subdomain>.<base domain>
 *   /c/<subdomain>        the older form of the same link, redirects the same way
 *   /store/<store-slug>   redirects to the store's theme preview (theme-<template>.<base domain>)
 *
 * Each redirect also drops the old client cookie, so the main domain stops showing that client.
 */
class StoreEntryController extends Controller
{
    public function redirectOldClientLink(Request $request, string $subdomain): RedirectResponse
    {
        return $this->toClient($request, Client::active()->where('subdomain', $subdomain)->firstOrFail());
    }

    /** Sends the visitor to the client's own address, keeping a same-site `to` path. */
    private function toClient(Request $request, Client $client, ?string $path = null): RedirectResponse
    {
        $request->session()->forget(Client::SESSION_KEY);

        return redirect()->away(rtrim($client->publicUrl(), '/').($path ?? $this->safePath($request)), 301)
            ->withCookie(cookie()->forget(Client::COOKIE))
            ->withCookie(cookie()->forget(Storefront::COOKIE));
    }

    /**
     * A standing link to the Simple List template's bulk order page: it opens /shop on the
     * subdomain of the first client running that template.
     */
    public function bulkOrder(Request $request): RedirectResponse
    {
        $client = Client::active()
            ->whereHas('storefront', fn ($query) => $query->where('template', 'simple'))
            ->orderBy('id')
            ->firstOrFail();

        return $this->toClient($request, $client, '/shop');
    }

    /** A client's subdomain, or else a store's slug (which opens that store's theme preview). */
    public function enter(Request $request, string $subdomain): RedirectResponse
    {
        if ($client = Client::active()->where('subdomain', $subdomain)->first()) {
            return $this->toClient($request, $client);
        }

        $store = Storefront::active()->where('slug', $subdomain)->firstOrFail();
        $theme = $store->theme();
        abort_unless($theme?->is_active, 404);

        return $this->toClient($request, $theme);
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
