<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Clients\ClientDatabase;
use App\Clients\ClientQuota;
use App\Filament\Support\DateRangeFilter;
use App\Filament\Support\RowActions;
use App\Models\Client;
use App\Models\Storefront;
use App\Sync\SyncAccess;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => SyncAccess::withStatus($query))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold')
                    ->description(fn (Client $r) => $r->subdomain.'.'.Client::baseDomain()),
                TextColumn::make('storefront.name')->label('Store')->badge()->color('gray')->placeholder('—')->sortable(),
                ViewColumn::make('public_url')->label('Storefront')->state(fn (Client $r) => $r->publicUrl())
                    ->view('filament.tables.columns.link-copy')
                    ->viewData(['label' => 'Open store'])->disabledClick(),
                ViewColumn::make('login_url')->label('Client login')->state(fn (Client $r) => $r->loginUrl())
                    ->view('filament.tables.columns.link-copy')
                    ->viewData(['label' => 'Open login', 'icon' => 'heroicon-m-arrow-right-end-on-rectangle'])->disabledClick(),
                TextColumn::make('plan.name')->label('Plan')->placeholder('No plan')
                    ->description(fn (Client $r) => $r->plan?->price_label),
                TextColumn::make('usage')->label('Usage')->state(fn (Client $r) => ClientQuota::for($r)->productLabel().' products')
                    ->description(fn (Client $r) => ClientQuota::for($r)->imageLabel().' images')
                    ->color(fn (Client $r) => ClientQuota::for($r)->atProductLimit() || ClientQuota::for($r)->atImageLimit() ? 'danger' : 'gray'),
                // The shop PC's sync agent (docs/SYNC.md), as on the client's page.
                TextColumn::make('sync_status')->label('Shop sync')->badge()
                    ->state(fn (Client $r) => app(SyncAccess::class)->status($r)['label'])
                    ->color(fn (Client $r) => app(SyncAccess::class)->status($r)['color'])
                    ->description(fn (Client $r) => ($seen = $r->syncAgent?->last_seen_at) ? 'Last sync '.$seen->diffForHumans() : null)
                    ->tooltip(fn (Client $r) => $r->syncAgent?->last_report['message'] ?? null)
                    ->toggleable(),
                TextColumn::make('users_count')->counts('users')->label('Staff')->alignRight(),
                TextColumn::make('customers_count')->counts('customers')->label('Customers')->alignRight(),
                TextColumn::make('database')->label('Database')->state(fn (Client $r) => app(ClientDatabase::class)->isolated() ? $r->databaseName() : 'shared')
                    ->color('gray')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Created')->date('d M Y')->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                SelectFilter::make('storefront_id')->label('Store')->options(fn () => Storefront::orderBy('name')->pluck('name', 'id')),
                DateRangeFilter::range('created_at', 'Created'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                RowActions::group([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('visit')->label('Visit store')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->hidden(fn (Client $r) => $r->trashed())
                        ->url(fn (Client $r) => $r->publicUrl(), shouldOpenInNewTab: true),
                    Action::make('toggle')->label(fn (Client $r) => $r->is_active ? 'Deactivate' : 'Activate')
                        ->icon(fn (Client $r) => $r->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                        ->color(fn (Client $r) => $r->is_active ? 'danger' : 'success')
                        ->hidden(fn (Client $r) => $r->trashed())
                        ->requiresConfirmation()
                        ->action(fn (Client $r) => $r->update(['is_active' => ! $r->is_active])),
                    DeleteAction::make()
                        ->modalHeading(fn (Client $r) => 'Delete '.$r->name.'?')
                        ->modalDescription('The storefront and client login stop working straight away. Staff, customers and the client database are kept, and the client can be restored from the "Deleted items" filter.')
                        ->modalSubmitActionLabel('Delete')
                        ->successNotificationTitle('Client deleted'),
                    RestoreAction::make(),
                ]),
            ]);
    }
}
