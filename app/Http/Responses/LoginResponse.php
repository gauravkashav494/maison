<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportRedirects\Redirector;
use Throwable;

/**
 * After signing in, return to the page that asked for it — but only if this account may open
 * it. Otherwise the panel's home: a platform staff member whose session started on a Super
 * Admin page (say /users, left open by someone else in that browser) would land on a bare 403.
 */
class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $home = Filament::getUrl();
        $intended = session()->get('url.intended');

        if ($intended && ! $this->canOpen($intended)) {
            session()->forget('url.intended');
        }

        return redirect()->intended($home);
    }

    /** Whether the signed-in account may open the panel page behind this URL. */
    private function canOpen(string $url): bool
    {
        try {
            $class = Route::getRoutes()->match(Request::create($url))->getControllerClass();
        } catch (Throwable) {
            return false; // not a route at all: the home page is the better landing
        }

        return match (true) {
            ! $class => true,
            is_subclass_of($class, ResourcePage::class) => $class::getResource()::canAccess(),
            is_subclass_of($class, Page::class) => $class::canAccess(),
            default => true, // not a panel page: its own middleware decides
        };
    }
}
