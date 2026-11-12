<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Filament\Support\RowActions;
use App\Models\Project;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('after_image')->label('')->state(fn (Project $r) => Media::url($r->after_image))->square()->imageSize(44),
                TextColumn::make('title')->searchable()->sortable()->description(fn (Project $r) => $r->location),
                TextColumn::make('service.name')->label('Service')->badge()->color('gray'),
                TextColumn::make('completed_on')->date('d M Y')->sortable(),
                ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([RowActions::group([EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make([...RowActions::trashBulk()])]);
    }
}
