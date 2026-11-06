<?php

namespace App\Filament\Widgets;

use App\Clients\ClientContext;
use App\Clients\ClientTotals;
use App\Filament\Pages\ClientTotalsReport;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Storefronts\StorefrontResource;
use App\Models\Client;
use App\Models\Storefront;
use Filament\Widgets\Widget;

/** The platform at a glance for the SuperAdmin: clients, stores and totals, nothing itemised. */
class PlatformStats extends Widget
{
    protected string $view = 'filament.widgets.store-stats';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return app(ClientContext::class)->isSuperAdmin();
    }

    protected function getViewData(): array
    {
        $clients = Client::count();
        $active = Client::where('is_active', true)->count();
        $new = Client::where('created_at', '>=', now()->startOfMonth())->count();
        $totals = ClientTotals::all();

        return ['stats' => [
            ['label' => 'Clients', 'value' => $clients, 'description' => $active.' active · '.($clients - $active).' suspended', 'icon' => 'briefcase', 'color' => 'is-blue', 'url' => ClientResource::getUrl('index')],
            ['label' => 'New this month', 'value' => $new, 'description' => 'Clients added since the 1st', 'icon' => 'user-plus', 'color' => 'is-green', 'url' => ClientResource::getUrl('index')],
            ['label' => 'Stores', 'value' => Storefront::count(), 'description' => Client::whereNotNull('storefront_id')->distinct()->count('storefront_id').' in use', 'icon' => 'building-storefront', 'color' => 'is-purple', 'url' => StorefrontResource::getUrl('index')],
            ['label' => 'Revenue, all clients', 'value' => money($totals->sum('revenue')), 'description' => number_format($totals->sum('orders')).' orders in total', 'icon' => 'banknotes', 'color' => 'is-orange', 'url' => ClientTotalsReport::getUrl()],
        ]];
    }
}
