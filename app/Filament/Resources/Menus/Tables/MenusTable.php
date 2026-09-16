<?php

namespace App\Filament\Resources\Menus\Tables;

use App\Models\Menu;
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
                TextColumn::make('location')->formatStateUsing(fn (string $state) => Menu::LOCATIONS[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('all_items_count')->counts('allItems')->label('Items')->alignRight(),
            ])
            ->recordActions([EditAction::make()->label('Manage items')]);
    }
}
