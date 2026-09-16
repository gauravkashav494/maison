<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(2)->schema([
                Select::make('product_id')->relationship('product', 'name')->searchable()->preload()->required(),
                Select::make('rating')->options([5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star'])->required()->native(false),
                TextInput::make('name')->required()->maxLength(80),
                TextInput::make('email')->email()->maxLength(190),
            ]),
            TextInput::make('title')->maxLength(120),
            Textarea::make('body')->rows(5)->required(),
            Toggle::make('is_approved')->label('Approved (visible on the product page)'),
        ]);
    }
}
