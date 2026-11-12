<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The platform panel at /admin: where a SuperAdmin runs the platform.
 *
 * It holds clients, stores, plans and platform accounts, and nothing belonging to a client:
 * their orders, customers, products and content are on /client, for their own staff. How a
 * client is doing appears here as totals only (ClientTotalsReport).
 */
class AdminPanelProvider extends PanelProvider
{
    public const RESOURCES = [
        \App\Filament\Resources\Clients\ClientResource::class,
        \App\Filament\Resources\ClientUsers\ClientUserResource::class,
        \App\Filament\Resources\Storefronts\StorefrontResource::class,
        \App\Filament\Resources\Plans\PlanResource::class,
        \App\Filament\Resources\Stores\StoreResource::class,
        \App\Filament\Resources\Users\UserResource::class,
        \App\Filament\Resources\PlatformActivities\PlatformActivityResource::class,
    ];

    public const PAGES = [
        Dashboard::class,
        \App\Filament\Pages\ClientTotalsReport::class,
        \App\Filament\Pages\Templates::class,
        \App\Filament\Pages\SiteSettings::class,
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            // The root of the main domain (ecomvision.test/, /login, /clients ...). Main domain
            // only: client subdomains have their store and the client panel, never this one.
            ->path('')
            ->domain(\App\Models\Storefront::baseDomain())
            ->authGuard('admin')
            ->resourceCreatePageRedirect('index')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(\App\Filament\Auth\Login::class)
            ->maxContentWidth(Width::Full)
            ->brandName(fn () => config('app.name'))
            ->brandLogo(fn () => view('filament.partials.brand'))
            ->favicon(null)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">', scopes: \App\Filament\Auth\Login::class)
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.partials.topbar-user'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.partials.sidebar-footer'))
            ->navigationGroups(['Clients', 'Platform', 'Settings'])
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
            ])
            // Listed, not discovered: a new screen stays off the platform panel until it is added here.
            ->resources(self::RESOURCES)
            ->pages(self::PAGES)
            ->widgets([
                \App\Filament\Widgets\PlatformStats::class,
                \App\Filament\Widgets\RecentClients::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\DiscourageSearchEngines::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\ApplyAdminScope::class,
            ])
            ->persistentMiddleware([
                \App\Http\Middleware\ApplyAdminScope::class,
            ]);
    }
}
