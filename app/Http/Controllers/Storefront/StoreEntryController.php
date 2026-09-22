<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Store entry links for hosts without wildcard DNS: /store/<slug> pins the store in a cookie
 * for a year and sends the visitor to the home page (or ?to=/path) of that store on this domain.
 */
class StoreEntryController extends Controller
{
    public function enter(Request $request, string $slug): RedirectResponse
    {
        $store = Storefront::active()->where('slug', $slug)->firstOrFail();
        $to = (string) $request->query('to', '/');
        $to = str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/';

        return redirect()->to($to)->withCookie(cookie(Storefront::COOKIE, $store->slug, 60 * 24 * 365));
    }

    public function exit(): RedirectResponse
    {
        return redirect()->to('/')->withCookie(cookie()->forget(Storefront::COOKIE));
    }
}
