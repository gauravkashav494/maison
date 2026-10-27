<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Clients\ClientDatabase;
use App\Clients\ClientQuota;
use App\Models\Client;
use App\Models\Storefront;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold')
                    ->description(fn (Client $r) => $r->slug),
                TextColumn::make('storefront.name')->label('Store')->badge()->color('gray')->placeholder('—')->sortable(),
                TextColumn::make('plan.name')->label('Plan')->placeholder('No plan')
                    ->description(fn (Client $r) => $r->plan?->price_label),
                TextColumn::make('public_url')->label('Address')->state(fn (Client $r) => $r->publicUrl())
                    ->url(fn (Client $r) => $r->publicUrl(), shouldOpenInNewTab: true)->color('info')->copyable()->icon('heroicon-o-arrow-top-right-on-square')
                    ->description(fn (Client $r) => $r->publicUrl() === $r->entryUrl() ? null : 'or '.$r->entryUrl()),
                TextColumn::make('usage')->label('Usage')->state(fn (Client $r) => ClientQuota::for($r)->productLabel().' products')
                    ->description(fn (Client $r) => ClientQuota::for($r)->imageLabel().' images')
                    ->color(fn (Client $r) => ClientQuota::for($r)->atProductLimit() || ClientQuota::for($r)->atImageLimit() ? 'danger' : 'gray'),
                TextColumn::make('users_count')->counts('users')->label('Staff')->alignRight(),
                TextColumn::make('customers_count')->counts('customers')->label('Customers')->alignRight(),
                TextColumn::make('database')->label('Database')->state(fn (Client $r) => app(ClientDatabase::class)->isolated() ? $r->databaseName() : 'shared')
                    ->color('gray')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Created')->date('d M Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('storefront_id')->label('Store')->options(fn () => Storefront::orderBy('name')->pluck('name', 'id')),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                Action::make('manage')->label('Manage')->icon('heroicon-o-arrow-right-circle')
                    ->url(fn (Client $r) => url('/admin?switch_client='.$r->id))
                    ->tooltip('Work inside the data of this client'),
                Action::make('toggle')->label(fn (Client $r) => $r->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Client $r) => $r->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (Client $r) => $r->is_active ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Client $r) => $r->update(['is_active' => ! $r->is_active])),
                EditAction::make(),
            ]);
    }
}
