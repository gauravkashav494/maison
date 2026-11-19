<?php

namespace App\Http\Middleware;

use App\Filament\Auth\Login;
use App\Support\PanelSignOut;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Notifications\Notification;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Filament's panel sign-in check, but an account that was switched off while signed in (its
 * own account, or the whole store) is signed out and sent back to the sign-in page with the
 * reason, instead of being shown a bare "403 Forbidden". Any other refusal stays a 403.
 */
class Authenticate extends FilamentAuthenticate
{
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();
        $reason = $user ? Login::deactivatedMessage($user) : null;

        if ($reason === null) {
            parent::authenticate($request, $guards);

            return;
        }

        PanelSignOut::guard($request, Filament::getAuthGuard());

        Notification::make()->danger()->title('You have been signed out')->body($reason)->persistent()->send();

        throw new HttpResponseException(redirect()->to(Filament::getLoginUrl()));
    }
}
