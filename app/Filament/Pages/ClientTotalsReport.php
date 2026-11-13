<?php

namespace App\Filament\Pages;

use App\Clients\ClientTotals;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Support\RowActions;
use App\Models\Client;
use App\Models\Storefront;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** How each client is doing, in numbers. The SuperAdmin's only view of client activity. */
class ClientTotalsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.client-totals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Clients';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Client totals';

    protected static ?string $title = 'Client totals';

    protected static ?string $slug = 'client-totals';

    protected ?string $subheading = 'Revenue excludes cancelled orders. Individual orders, customers and products stay with each client.';

    /** Counts already read this request, by client id: each client's database is opened once. */
    protected array $totals = [];

    public static function canAccess(): bool
    {
        return (bool) filament()->auth()->user()?->isSuperAdmin();
    }

    private function totalsFor(Client $client): array
    {
        return $this->totals[$client->id] ??= ClientTotals::for($client);
    }

    public function table(Table $table): Table
    {
        $count = fn (string $key, string $label) => TextColumn::make($key)->label($label)->alignRight()
            ->state(fn (Client $r) => $this->totalsFor($r)[$key])
            ->numeric()
            ->color(fn ($state) => $state ? null : 'gray');

        return $table
            ->query(Client::query()->with(['storefront', 'plan']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Client')->searchable()->sortable()->weight('bold')
                    ->description(fn (Client $r) => $r->subdomain.'.'.Client::baseDomain())
                    ->url(fn (Client $r) => ClientResource::getUrl('view', ['record' => $r])),
                TextColumn::make('storefront.name')->label('Store')->badge()->color('gray')->placeholder('—')->sortable(),
                TextColumn::make('plan.name')->label('Plan')->placeholder('No plan'),
                TextColumn::make('is_active')->label('Status')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Active' : 'Suspended')
                    ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                $count('products', 'Products'),
                $count('services', 'Services'),
                $count('orders', 'Orders'),
                TextColumn::make('revenue')->label('Revenue')->alignRight()->weight('medium')
                    ->state(fn (Client $r) => money($this->totalsFor($r)['revenue'])),
                $count('customers', 'Customers'),
                $count('staff', 'Staff'),
            ])
            ->filters([
                SelectFilter::make('storefront_id')->label('Store')->options(fn () => Storefront::orderBy('name')->pluck('name', 'id')),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                RowActions::group([
                    Action::make('view')->label('View')->icon('heroicon-o-eye')
                        ->url(fn (Client $r) => ClientResource::getUrl('view', ['record' => $r])),
                    Action::make('edit')->label('Edit')->icon('heroicon-o-pencil-square')
                        ->url(fn (Client $r) => ClientResource::getUrl('edit', ['record' => $r])),
                    Action::make('visit')->label('Visit store')->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (Client $r) => $r->publicUrl(), shouldOpenInNewTab: true),
                ]),
            ])
            ->emptyStateHeading('No clients yet')
            ->emptyStateIcon('heroicon-o-chart-bar');
    }
}
