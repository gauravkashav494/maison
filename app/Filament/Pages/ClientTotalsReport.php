<?php

namespace App\Filament\Pages;

use App\Clients\ClientTotals;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** How each client is doing, in numbers. The SuperAdmin's only view of client activity. */
class ClientTotalsReport extends Page
{
    protected string $view = 'filament.pages.client-totals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Clients';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Client totals';

    protected static ?string $title = 'Client totals';

    protected static ?string $slug = 'client-totals';

    public static function canAccess(): bool
    {
        return (bool) filament()->auth()->user()?->isSuperAdmin();
    }

    protected function getViewData(): array
    {
        return ['rows' => ClientTotals::all()];
    }
}
