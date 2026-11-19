<?php

namespace App\Http\Middleware;

use App\Support\PanelSignOut;
use Filament\Facades\Filament;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Auth\AuthenticationException;

/**
 * Filament's session check (signs a panel account out once its password was changed elsewhere),
 * but it signs out only the panel account instead of flushing the whole session, so a shopper
 * signed in to the storefront in the same browser stays signed in. See PanelSignOut.
 */
class AuthenticatePanelSession extends AuthenticateSession
{
    protected function logout($request)
    {
        PanelSignOut::guard($request, Filament::getAuthGuard(), thisDeviceOnly: true);

        throw new AuthenticationException(
            'Unauthenticated.', [$this->auth->getDefaultDriver()], $this->redirectTo($request)
        );
    }
}
