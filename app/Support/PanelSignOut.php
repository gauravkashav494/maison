<?php

namespace App\Support;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs one panel account (admin or client guard) out without touching the rest of the session.
 *
 * The storefront and both panels share one browser session, one key per guard. Invalidating the
 * session (what Filament's own logout does) would also sign out a shopper signed in to the shop
 * in another tab, so only this guard and the panel's own session data are removed.
 */
final class PanelSignOut
{
    /** Panel-only session data, dropped once no panel account is left signed in. */
    private const PANEL_KEYS = [ClientContext::SESSION_KEY, StoreContext::SESSION_KEY];

    /**
     * @param  bool  $thisDeviceOnly  keep the account's remember-me token, so its other browsers stay
     *                                signed in (used when the session check finds a changed password)
     */
    public static function guard(Request $request, string $guard, bool $thisDeviceOnly = false): void
    {
        $thisDeviceOnly ? Auth::guard($guard)->logoutCurrentDevice() : Auth::guard($guard)->logout();

        $session = $request->session();
        // Written by AuthenticateSession for this guard only.
        $session->forget('password_hash_'.$guard);

        if (! Auth::guard('admin')->check() && ! Auth::guard('client')->check()) {
            $session->forget(self::PANEL_KEYS);
        }

        // A new session id, same data: the shopper's sign-in (if any) carries over.
        $session->regenerate();
    }
}
