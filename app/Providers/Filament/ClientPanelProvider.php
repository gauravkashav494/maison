<?php

namespace App\Providers\Filament;

use App\Clients\ClientContext;
use App\Filament\Auth\Login;
use App\Filament\Pages\GroceryHomepageSettings;
use App\Filament\Pages\GrocerySettings;
use App\Filament\Pages\HeritageHomepageSettings;
use App\Filament\Pages\HeritageSettings;
use App\Filament\Pages\HomepageSettings;
use App\Filament\Pages\PlumbingHomepageSettings;
use App\Filament\Pages\PlumbingServicesHomepageSettings;
use App\Filament\Pages\PlumbingServicesSettings;
use App\Filament\Pages\PlumbingSettings;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\ClientRoles\ClientRoleResource;
use App\Filament\Resources\ClientUsers\ClientUserResource;
use App\Filament\Resources\Collections\CollectionResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Faqs\FaqResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\Menus\MenuResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Resources\ServiceAreas\ServiceAreaResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Filament\Widgets\ClientUsage;
use App\Filament\Widgets\RecentOrders;
use App\Filament\Widgets\ServiceStats;
use App\Filament\Widgets\StoreLink;
use App\Filament\Widgets\StoreStats;
use App\Http\Middleware\ApplyAdminScope;
use App\Http\Middleware\DiscourageSearchEngines;
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
        ProductResource::class,
        CategoryResource::class,
        CollectionResource::class,
        CouponResource::class,
        OrderResource::class,
        ReviewResource::class,
        ServiceResource::class,
        ServiceAreaResource::class,
        ServiceRequestResource::class,
        ProjectResource::class,
        TestimonialResource::class,
        PageResource::class,
        PostResource::class,
        FaqResource::class,
        MenuResource::class,
        StoreResource::class,
        ContactMessageResource::class,
        SubscriberResource::class,
        CustomerResource::class,
        ClientUserResource::class,
        ClientRoleResource::class,
    ];

    /** Appearance and content settings for the templates; each page checks its own access. */
    public const PAGES = [
        Dashboard::class,
        SiteSettings::class,
        HomepageSettings::class,
        GrocerySettings::class,
        GroceryHomepageSettings::class,
        HeritageSettings::class,
        HeritageHomepageSettings::class,
        PlumbingSettings::class,
        PlumbingHomepageSettings::class,
        PlumbingServicesSettings::class,
        PlumbingServicesHomepageSettings::class,
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('client')
            ->path('client')
            ->authGuard('client')
            ->resourceCreatePageRedirect('index')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->maxContentWidth(Width::Full)
            ->brandName(fn () => app(ClientContext::class)->client()?->name ?? config('app.name'))
            ->brandLogo(fn () => view('filament.partials.brand'))
            ->favicon(null)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">', scopes: Login::class)
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('filament.partials.client-badge'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.partials.topbar-user'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.partials.sidebar-footer'))
            ->navigationGroups(['Sales', 'Catalogue', 'Services', 'Content', 'Marketing', 'Appearance', 'Team', 'Settings'])
            ->colors([
                // Sidebar navy (--navy in the admin theme) at 600, the shade buttons, toggles and checkboxes use.
                'primary' => [
                    50 => '#eef2f8', 100 => '#dbe3ef', 200 => '#b9c7dd', 300 => '#8ca2c3', 400 => '#5c7aa3',
                    500 => '#34527d', 600 => '#1c3557', 700 => '#172c49', 800 => '#12233a', 900 => '#0e1b2d', 950 => '#08111d',
                ],
                'gray' => Color::Slate,
            ])
            ->resources(self::RESOURCES)
            ->pages(self::PAGES)
            ->widgets([
                StoreLink::class,
                ClientUsage::class,
                StoreStats::class,
                ServiceStats::class,
                RecentOrders::class,
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
                DiscourageSearchEngines::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                ApplyAdminScope::class,
            ])
            ->persistentMiddleware([
                ApplyAdminScope::class,
            ]);
    }
}
