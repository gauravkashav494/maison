<?php

namespace App\Filament\Resources\Menus\Tables;

use App\Templates\TemplateManager;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('template')->label('Template')->formatStateUsing(fn (?string $state) => $state ? app(TemplateManager::class)->get($state)->name() : '—')->badge()->color(fn (?string $state) => $state === app(TemplateManager::class)->activeId() ? 'success' : 'gray'),
                TextColumn::make('location')->formatStateUsing(fn (string $state) => app(TemplateManager::class)->allMenuLocations()[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('all_items_count')->counts('allItems')->label('Items')->alignRight(),
            ])
            ->defaultSort('template')
            ->recordActions([EditAction::make()->label('Manage items')]);
    }
}
