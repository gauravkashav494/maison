<?php

namespace App\Filament\Resources\Storefronts\Tables;

use App\Filament\Support\RowActions;
use App\Models\Client;
use App\Models\Storefront;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class StorefrontsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('template')->label('Template')->formatStateUsing(fn (Storefront $r) => $r->template_name)->badge()->color('gray')
                    ->description(fn (Storefront $r) => $r->supportsServices() ? 'Service business' : 'Catalogue store'),
                TextColumn::make('clients_count')->label('Clients')->counts('clients')->alignRight()
                    ->description(fn (Storefront $r) => Client::where('storefront_id', $r->id)->where('is_active', true)->count().' active')
                    ->tooltip('Each client has its own database, staff and customers'),
                ViewColumn::make('theme_url')->label('Theme preview')->state(fn (Storefront $r) => $r->theme()?->publicUrl())
                    ->view('filament.tables.columns.link-copy')->viewData(['placeholder' => 'Not set up'])->disabledClick(),
                TextColumn::make('product_limit')->label('Default limits')->placeholder('Unlimited')
                    ->formatStateUsing(fn (?int $state) => $state === null ? 'Unlimited products' : number_format($state).' products')
                    ->description(fn (Storefront $r) => $r->storage_limit_mb === null ? 'Unlimited storage' : number_format($r->storage_limit_mb).' MB storage'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Created')->date('d M Y')->sortable(),
            ])
            ->recordActions([
                RowActions::group([
                    EditAction::make(),
                    Action::make('preview')->label('Preview theme')->icon('heroicon-o-arrow-top-right-on-square')
                        ->visible(fn (Storefront $r) => $r->theme() !== null)
                        ->url(fn (Storefront $r) => $r->theme()?->publicUrl(), shouldOpenInNewTab: true),
                    Action::make('toggle')->label(fn (Storefront $r) => $r->is_active ? 'Deactivate' : 'Activate')
                        ->icon(fn (Storefront $r) => $r->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                        ->color(fn (Storefront $r) => $r->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(fn (Storefront $r) => $r->update(['is_active' => ! $r->is_active])),
                ]),
            ]);
    }
}
