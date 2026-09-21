<?php

namespace App\Filament\Resources\ServiceAreas\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ServiceAreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Service area')->tabs([
                    Tab::make('Area')
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Grid::make(2)->schema([
                                ...Fields::nameAndSlug('name', 'City / area', scopeColumn: 'template'),
                                Fields::templateVisibility()->live(),
                                TextInput::make('state')->maxLength(80),
                                TextInput::make('response_time')->label('Typical emergency response')->placeholder('60–90 min')->maxLength(60),
                                Toggle::make('is_active')->label('Visible')->default(true)->inline(false),
                                TextInput::make('sort_order')->numeric()->default(0),
                            ]),
                            TagsInput::make('localities')->label('Localities covered')->placeholder('Add a locality and press Enter'),
                            Textarea::make('excerpt')->label('Short description')->rows(2)->maxLength(300),
                            RichEditor::make('description')->label('Page content'),
                            Fields::image('image', 'Image (1200×800)', 'plumbing-services'),
                        ]),
                    Fields::seoTab(),
                ]),
            ]);
    }
}
