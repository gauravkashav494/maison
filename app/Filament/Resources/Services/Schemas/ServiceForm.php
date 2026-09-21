<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Filament\Pages\PlumbingServicesHomepageSettings;
use App\Filament\Support\Fields;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        $icons = array_combine(PlumbingServicesHomepageSettings::ICONS, array_map(fn ($i) => str($i)->headline()->value(), PlumbingServicesHomepageSettings::ICONS));

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Service')->tabs([
                    Tab::make('Service')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->schema([
                            Grid::make(2)->schema([
                                ...Fields::nameAndSlug('name', 'Service name', scopeColumn: 'template'),
                                Fields::templateVisibility()->live(),
                                Select::make('icon')->options($icons)->native(false)->helperText('Line icon shown on cards, the category rail and the booking flow.'),
                                Toggle::make('is_active')->label('Visible')->default(true)->inline(false),
                                Toggle::make('is_popular')->label('Popular (shown first on the homepage)')->inline(false),
                                Toggle::make('is_emergency')->label('Emergency service')->helperText('Used by the emergency page, quick action and red accents. Keep one.')->inline(false),
                                TextInput::make('sort_order')->numeric()->default(0),
                            ]),
                            Textarea::make('excerpt')->label('Short description')->rows(2)->maxLength(300)->required()->helperText('One or two lines shown on cards and under the service title.'),
                            RichEditor::make('description')->label('About this service'),
                            Fields::image('image', 'Image (1200×800)', 'plumbing-services'),
                        ]),
                    Tab::make('Details')
                        ->icon('heroicon-o-list-bullet')
                        ->schema([
                            Section::make('Common problems')->description('Shown as a checklist on the service page and used by the service search.')->schema([
                                TagsInput::make('problems')->label('Problems')->placeholder('Add a problem and press Enter'),
                            ]),
                            Section::make('What’s included')->schema([
                                TagsInput::make('included')->label('Included')->placeholder('Add an item and press Enter'),
                            ]),
                            Section::make('Service-specific FAQs')->description('Optional. When empty, the general FAQs are shown instead.')->schema([
                                Repeater::make('faqs')->label('')->schema([
                                    TextInput::make('question')->required()->maxLength(200),
                                    Textarea::make('answer')->rows(3)->required(),
                                ])->collapsible()->itemLabel(fn (array $state): ?string => $state['question'] ?? null)->defaultItems(0),
                            ]),
                            TextInput::make('price_note')->label('Price note (optional)')->maxLength(120)->helperText('e.g. "Visit charge ₹199, adjusted in the final bill". Leave blank to show no pricing.'),
                        ]),
                    Fields::seoTab(),
                ]),
            ]);
    }
}
