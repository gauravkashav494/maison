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
            ->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('filament.partials.topbar-user'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.partials.sidebar-footer'))
            ->navigationGroups(['Sales', 'Catalogue', 'Content', 'Marketing', 'Appearance', 'Settings'])
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
                    ->sort(11)
                    ->icon('heroicon-o-bars-3')
                    ->url(fn () => MenuResource::getUrl('index', ['tab' => 'fashion'])),
                NavigationItem::make('Grocery · Navigation')
                    ->group('Appearance')
                    ->sort(22)
                    ->icon('heroicon-o-bars-3')
                    ->url(fn () => MenuResource::getUrl('index', ['tab' => 'grocery'])),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                StoreStats::class,
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
            ]);
    }
}
