<?php

namespace App\Http\Controllers\Admin;

use App\Support\PanelSignOut;
use Filament\Auth\Http\Controllers\LogoutController;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;

/**
 * Filament's panel logout, but only the panel account is signed out (see PanelSignOut), so a
 * shopper signed in to the storefront in the same browser stays signed in.
 * Bound in place of Filament's controller in AppServiceProvider.
 */
class PanelLogoutController extends LogoutController
{
    public function __invoke(): LogoutResponse
    {
        PanelSignOut::guard(request(), Filament::getAuthGuard());

        return app(LogoutResponse::class);
    }
}
