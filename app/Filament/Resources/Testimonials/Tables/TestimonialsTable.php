<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Models\Testimonial;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (Testimonial $r) => $r->location),
                TextColumn::make('rating')->state(fn (Testimonial $r) => str_repeat('★', $r->rating))->color('warning'),
                TextColumn::make('service.name')->label('Service')->badge()->color('gray'),
                TextColumn::make('body')->label('Review')->limit(60)->wrap(),
                ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
