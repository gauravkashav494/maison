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
 * The client panel at /client: where a Client Admin and their Admin Staff run their own store.
 *
 * It uses the `client` guard, so a SuperAdmin session cannot reach it and a session here is
 * never mistaken for a platform account. Only the client's own content is registered —
 * clients, plans, storefronts, super admins and template activation stay on /admin — and
 * every resource is scoped to the signed-in account's client by ApplyAdminScope plus the
 * policies, not by what is left out of the menu.
 */
class ClientPanelProvider extends PanelProvider
{
    /** Content a client manages for its own store. */
    public const RESOURCES = [
        \App\Filament\Resources\Products\ProductResource::class,
        \App\Filament\Resources\Categories\CategoryResource::class,
        \App\Filament\Resources\Collections\CollectionResource::class,
        \App\Filament\Resources\Coupons\CouponResource::class,
        \App\Filament\Resources\Orders\OrderResource::class,
        \App\Filament\Resources\Reviews\ReviewResource::class,
        \App\Filament\Resources\Services\ServiceResource::class,
        \App\Filament\Resources\ServiceAreas\ServiceAreaResource::class,
        \App\Filament\Resources\ServiceRequests\ServiceRequestResource::class,
        \App\Filament\Resources\Projects\ProjectResource::class,
        \App\Filament\Resources\Testimonials\TestimonialResource::class,
        \App\Filament\Resources\Pages\PageResource::class,
        \App\Filament\Resources\Posts\PostResource::class,
        \App\Filament\Resources\Faqs\FaqResource::class,
        \App\Filament\Resources\Menus\MenuResource::class,
        \App\Filament\Resources\ContactMessages\ContactMessageResource::class,
        \App\Filament\Resources\Subscribers\SubscriberResource::class,
        \App\Filament\Resources\Customers\CustomerResource::class,
        \App\Filament\Resources\ClientUsers\ClientUserResource::class,
    ];

    /** Appearance and content settings for the templates; each page checks its own access. */
    public const PAGES = [
        Dashboard::class,
        \App\Filament\Pages\SiteSettings::class,
        \App\Filament\Pages\HomepageSettings::class,
        \App\Filament\Pages\GrocerySettings::class,
        \App\Filament\Pages\GroceryHomepageSettings::class,
        \App\Filament\Pages\HeritageSettings::class,
        \App\Filament\Pages\HeritageHomepageSettings::class,
        \App\Filament\Pages\PlumbingSettings::class,
        \App\Filament\Pages\PlumbingHomepageSettings::class,
        \App\Filament\Pages\PlumbingServicesSettings::class,
        \App\Filament\Pages\PlumbingServicesHomepageSettings::class,
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('client')
            ->path('client')
            ->authGuard('client')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(\App\Filament\Auth\Login::class)
            ->maxContentWidth(Width::Full)
            ->brandName(fn () => app(\App\Clients\ClientContext::class)->client()?->name ?? setting('site.name', config('app.name')))
            ->brandLogo(fn () => view('filament.partials.brand'))
            ->favicon(null)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">', scopes: \App\Filament\Auth\Login::class)
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('filament.partials.client-badge'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.partials.topbar-user'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.partials.sidebar-footer'))
            ->navigationGroups(['Sales', 'Catalogue', 'Services', 'Content', 'Marketing', 'Appearance', 'Team', 'Settings'])
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->resources(self::RESOURCES)
            ->pages(self::PAGES)
            ->widgets([
                \App\Filament\Widgets\StoreLink::class,
                \App\Filament\Widgets\ClientUsage::class,
                \App\Filament\Widgets\StoreStats::class,
                \App\Filament\Widgets\ServiceStats::class,
                \App\Filament\Widgets\RecentOrders::class,
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
