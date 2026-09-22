<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(2)->schema([
                ...Fields::nameAndSlug('title', 'Title', scopeColumn: 'template'),
                Fields::templateVisibility()->live(),
                Select::make('service_id')->label('Service')->relationship('service', 'name', fn ($q) => $q->withoutGlobalScope(\App\Templates\Scopes\TemplateVisibility::NAME))->searchable()->preload()->native(false),
                TextInput::make('location')->maxLength(80)->placeholder('Sector 70, Mohali'),
                DatePicker::make('completed_on')->label('Completed on')->native(false),
                Toggle::make('is_active')->label('Visible')->default(true)->inline(false),
                TextInput::make('sort_order')->numeric()->default(0),
            ]),
            Textarea::make('description')->rows(3)->maxLength(600),
            Grid::make(2)->schema([
                Fields::image('before_image', 'Before (1000×750)', 'plumbing-services'),
                Fields::image('after_image', 'After (1000×750)', 'plumbing-services'),
            ]),
        ]);
    }
}
