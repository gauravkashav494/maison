<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(2)->schema([
                TextInput::make('name')->label('Customer name')->required()->maxLength(80),
                Fields::templateVisibility(),
                Select::make('rating')->options([5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star'])->default(5)->required()->native(false),
                Select::make('service_id')->label('Service')->relationship('service', 'name', fn ($q) => $q->withoutGlobalScope(\App\Templates\Scopes\TemplateVisibility::NAME))->searchable()->preload()->native(false),
                TextInput::make('location')->maxLength(80)->placeholder('Model Town, Ludhiana'),
                Toggle::make('is_active')->label('Visible')->default(true)->inline(false),
                TextInput::make('sort_order')->numeric()->default(0),
            ]),
            Textarea::make('body')->label('Review')->rows(4)->required()->maxLength(1000),
            Fields::image('image', 'Customer photo (optional)', 'plumbing-services'),
        ]);
    }
}
