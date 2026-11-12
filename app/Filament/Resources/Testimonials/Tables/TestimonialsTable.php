<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Filament\Support\RowActions;
use App\Models\Testimonial;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (Testimonial $r) => $r->location),
                TextColumn::make('rating')->state(fn (Testimonial $r) => str_repeat('★', $r->rating))->color('warning'),
                TextColumn::make('service.name')->label('Service')->badge()->color('gray'),
                TextColumn::make('body')->label('Review')->limit(60)->wrap(),
                ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([RowActions::group([EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make([...RowActions::trashBulk()])]);
    }
}
