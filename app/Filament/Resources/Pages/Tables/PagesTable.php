<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\RowActions;
use App\Models\Page;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->description(fn (Page $r) => '/'.$r->slug),
                TextColumn::make('template')->label('Layout')->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state) => Page::TEMPLATES[$state] ?? $state),
                ToggleColumn::make('is_active')->label('Published'),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('template')->label('Layout')->options(fn () => PageResource::layoutOptions())->multiple()->native(false),
                TrashedFilter::make(),
            ])
            ->recordActions([RowActions::group([EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make([...RowActions::trashBulk()])]);
    }
}
