<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Menus\MenuResource;
use App\Filament\Widgets\RecentOrders;
use App\Filament\Widgets\StoreStats;
use Filament\Http\Middleware\Authenticate;
use Filament\Navigation\NavigationItem;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(\App\Filament\Auth\Login::class)
            ->maxContentWidth(Width::Full)
            ->brandName(fn () => setting('site.name', config('app.name')))
            ->brandLogo(fn () => view('filament.partials.brand'))
            ->favicon(null)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">', scopes: \App\Filament\Auth\Login::class)
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('filament.partials.store-switcher'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.partials.topbar-user'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.partials.sidebar-footer'))
            ->navigationGroups(['Platform', 'Sales', 'Catalogue', 'Services', 'Content', 'Marketing', 'Appearance', 'Settings'])
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('Fashion · Navigation')
                    ->group('Appearance')
                    ->visible(fn () => app(\App\Admin\StoreContext::class)->allowsTemplate('fashion'))
                    ->sort(11)
                    ->icon('heroicon-o-bars-3')
                    ->url(fn () => MenuResource::getUrl('index', ['tab' => 'fashion'])),
                NavigationItem::make('Grocery · Navigation')
                    ->group('Appearance')
                    ->visible(fn () => app(\App\Admin\StoreContext::class)->allowsTemplate('grocery'))
                    ->sort(22)
                    ->icon('heroicon-o-bars-3')
                    ->url(fn () => MenuResource::getUrl('index', ['tab' => 'grocery'])),
                NavigationItem::make('Plumbing · Navigation')
                    ->group('Appearance')
                    ->visible(fn () => app(\App\Admin\StoreContext::class)->allowsTemplate('plumbing'))
                    ->sort(42)
                    ->icon('heroicon-o-bars-3')
                    ->url(fn () => MenuResource::getUrl('index', ['tab' => 'plumbing'])),
                NavigationItem::make('Plumbing Services · Navigation')
                    ->group('Appearance')
                    ->visible(fn () => app(\App\Admin\StoreContext::class)->allowsTemplate('plumbing-services'))
                    ->sort(52)
                    ->icon('heroicon-o-bars-3')
                    ->url(fn () => MenuResource::getUrl('index', ['tab' => 'plumbing-services'])),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\StoreLink::class,
                \App\Filament\Widgets\StoreUsage::class,
                \App\Filament\Widgets\PlatformStats::class,
                StoreStats::class,
                \App\Filament\Widgets\ServiceStats::class,
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
                \App\Http\Middleware\DiscourageSearchEngines::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\ApplyAdminStoreScope::class,
            ])
            ->persistentMiddleware([
                \App\Http\Middleware\ApplyAdminStoreScope::class,
            ]);
    }
}
