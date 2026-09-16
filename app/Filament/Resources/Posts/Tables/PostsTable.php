<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (Post $r) => Media::url($r->image))->square()->imageSize(44),
                TextColumn::make('title')->searchable()->sortable()->description(fn (Post $r) => $r->category),
                TextColumn::make('published_at')->dateTime('d M Y')->sortable(),
                ToggleColumn::make('is_featured')->label('Lead story'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
