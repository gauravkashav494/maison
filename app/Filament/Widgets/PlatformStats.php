<?php

namespace App\Filament\Widgets;

use App\Admin\PlatformModules;
use App\Clients\ClientTotals;
use App\Filament\Pages\ClientTotalsReport;
use App\Filament\Pages\Templates;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Storefronts\StorefrontResource;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Storefront;
use App\Templates\TemplateManager;
use Filament\Widgets\Widget;

/**
 * The platform at a glance: clients, stores, totals, plans and templates, nothing itemised.
 * Each tile belongs to one platform module and shows only to accounts holding that module, so
 * platform staff never see a figure or a link they could not open. A Super Admin holds every
 * module and sees every tile.
 */
class PlatformStats extends Widget
{
    protected string $view = 'filament.widgets.store-stats';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return collect(array_keys(PlatformModules::ALL))->contains(fn (string $module) => PlatformModules::current($module));
    }

    protected function getViewData(): array
    {
        $stats = [];

        if (PlatformModules::current(PlatformModules::CLIENTS)) {
            $clients = Client::count();
            $active = Client::where('is_active', true)->count();
            $new = Client::where('created_at', '>=', now()->startOfMonth())->count();

            $stats[] = ['label' => 'Clients', 'value' => $clients, 'description' => $active.' active · '.($clients - $active).' suspended', 'icon' => 'briefcase', 'color' => 'is-blue', 'url' => ClientResource::getUrl('index')];
            $stats[] = ['label' => 'New this month', 'value' => $new, 'description' => 'Clients added since the 1st', 'icon' => 'user-plus', 'color' => 'is-green', 'url' => ClientResource::getUrl('index', ['filters' => ['created_at' => ['created_from' => now()->startOfMonth()->toDateString()]]])];
        }

        if (PlatformModules::current(PlatformModules::STOREFRONTS)) {
            $stats[] = ['label' => 'Stores', 'value' => Storefront::count(), 'description' => Client::whereNotNull('storefront_id')->distinct()->count('storefront_id').' in use', 'icon' => 'building-storefront', 'color' => 'is-purple', 'url' => StorefrontResource::getUrl('index')];
        }

        if (PlatformModules::current(PlatformModules::REPORTS)) {
            $totals = ClientTotals::all();
            $stats[] = ['label' => 'Revenue, all clients', 'value' => money($totals->sum('revenue')), 'description' => number_format($totals->sum('orders')).' orders in total', 'icon' => 'banknotes', 'color' => 'is-orange', 'url' => ClientTotalsReport::getUrl()];
        }

        if (PlatformModules::current(PlatformModules::PLANS)) {
            $top = Plan::withCount('clients')->orderByDesc('clients_count')->orderBy('position')->first();
            $stats[] = ['label' => 'Plans', 'value' => Plan::count(), 'description' => $top && $top->clients_count > 0 ? 'Most clients on '.$top->name.' ('.$top->clients_count.')' : 'No clients on a plan yet', 'icon' => 'credit-card', 'color' => 'is-blue', 'url' => PlanResource::getUrl('index')];
        }

        if (PlatformModules::current(PlatformModules::TEMPLATES)) {
            $stats[] = ['label' => 'Templates', 'value' => app(TemplateManager::class)->all()->count(), 'description' => Storefront::distinct()->count('template').' used by a store', 'icon' => 'swatch', 'color' => 'is-green', 'url' => Templates::getUrl()];
        }

        return ['stats' => $stats];
    }
}
