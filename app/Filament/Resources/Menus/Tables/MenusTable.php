<?php

namespace App\Filament\Resources\Menus\Tables;

use App\Admin\StoreContext;
use App\Filament\Resources\Menus\MenuResource;
use App\Filament\Support\RowActions;
use App\Models\Menu;
use App\Templates\TemplateManager;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                // Every menu of a store has the store's template; the column only helps a super admin across stores.
                TextColumn::make('template')->label('Template')->visible(fn () => app(StoreContext::class)->template() === null)->formatStateUsing(fn (?string $state) => $state ? app(TemplateManager::class)->get($state)->name() : '—')->badge()->color('gray'),
                TextColumn::make('location')->formatStateUsing(fn (string $state) => app(TemplateManager::class)->menuLocationsFor(app(StoreContext::class)->template())[$state]
                    ?? app(TemplateManager::class)->allMenuLocations()[$state] ?? $state)->badge()->color('gray')
                    ->label('Shows on the store')
                    ->description(fn (Menu $record) => MenuResource::placement($record->location)),
                TextColumn::make('all_items_count')->counts('allItems')->label('Items')->alignRight(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([TrashedFilter::make()])
            ->recordActions([RowActions::group([
                EditAction::make()->label('Manage items'),
                Action::make('viewOnStore')->label('View on storefront')->icon('heroicon-o-arrow-top-right-on-square')->color('primary')
                    ->url(fn (Menu $record) => MenuResource::previewUrl($record->location, fullPage: true) ?? MenuResource::storefrontUrl(), shouldOpenInNewTab: true)
                    ->visible(fn () => MenuResource::storefrontUrl() !== null),
                ...RowActions::trash(),
            ])])
            ->toolbarActions([BulkActionGroup::make(RowActions::trashBulk())]);
    }
}
