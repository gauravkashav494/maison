<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Support\Fields;
use App\Models\Page;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        $is = fn (string $template) => fn (Get $get) => $get('template') === $template;

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Page')->persistTabInQueryString()->tabs([
                    Tab::make('Content')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Grid::make(2)->schema([
                                ...Fields::nameAndSlug('title', 'Title', scopeColumn: 'storefront_template'),
                                Fields::templateVisibility('storefront_template')->live(),
                                Select::make('template')
                                    ->options(Page::TEMPLATES)
                                    ->default('default')
                                    ->required()
                                    ->live()
                                    ->native(false)
                                    ->helperText('Controls the layout and which extra fields appear in the “Template blocks” tab.'),
                                TextInput::make('eyebrow')->maxLength(80)->helperText('Small label above the title.'),
                            ]),
                            Textarea::make('excerpt')->rows(2)->maxLength(400)->helperText('Intro shown under the title.'),
                            RichEditor::make('body')->helperText('Main copy. Legal pages render this as a document; other templates show it above their blocks.'),
                            Fields::image('image', 'Header image (optional)', 'pages'),
                            Toggle::make('is_active')->label('Published')->default(true),
                        ]),

                    Tab::make('Template blocks')
                        ->icon('heroicon-o-squares-plus')
                        ->schema([
                            // ---- About ----
                            Section::make('Story sections')->visible($is('about'))->schema([
                                Repeater::make('data.sections')->hiddenLabel()->schema([
                                    Select::make('layout')->options(['text-image' => 'Text left, image right', 'image-text' => 'Image left, text right'])->default('text-image')->native(false),
                                    TextInput::make('eyebrow'),
                                    TextInput::make('heading')->required()->helperText('Wrap a word in *asterisks* for italic serif.')->columnSpanFull(),
                                    Textarea::make('body')->rows(5)->required()->columnSpanFull(),
                                    Fields::image('image', 'Image (portrait)', 'pages')->columnSpanFull(),
                                ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state) => $state['heading'] ?? null),
                            ]),
                            Section::make('What we stand for')->visible($is('about'))->schema([
                                Repeater::make('data.values')->hiddenLabel()->schema([
                                    TextInput::make('title')->required(),
                                    Textarea::make('text')->rows(2)->required(),
                                ])->columns(2)->maxItems(6)->reorderable(),
                            ]),
                            Section::make('Sustainability & founders')->visible($is('about'))->columns(2)->schema([
                                TextInput::make('data.sustainability_heading')->label('Heading')->columnSpanFull(),
                                Textarea::make('data.sustainability_body')->label('Body')->rows(5)->columnSpanFull(),
                                Fields::image('data.sustainability_image', 'Image', 'pages')->columnSpanFull(),
                                Textarea::make('data.founder_quote')->label('Founder quote')->rows(2),
                                TextInput::make('data.founder_name')->label('Attribution'),
                            ]),

                            // ---- Contact ----
                            Section::make('Contact form')->visible($is('contact'))->schema([
                                TagsInput::make('data.subjects')->label('Subject options')->reorderable(),
                            ]),

                            // ---- Size guide ----
                            Section::make('Size tables')->visible($is('size-guide'))->schema([
                                Repeater::make('data.tables')->hiddenLabel()->schema([
                                    TextInput::make('name')->required(),
                                    TagsInput::make('columns')->label('Column headings')->required(),
                                    Repeater::make('rows')->label('Rows')->simple(TagsInput::make('cells')->label('Cells (one per column)'))->reorderable(),
                                ])->reorderable()->collapsible()->itemLabel(fn (array $state) => $state['name'] ?? null),
                                Repeater::make('data.how_to')->label('How to measure')->schema([
                                    TextInput::make('title')->required(),
                                    Textarea::make('text')->rows(2)->required(),
                                ])->columns(2)->reorderable(),
                            ]),

                            // ---- Care guide ----
                            Section::make('Materials')->visible($is('care-guide'))->schema([
                                Repeater::make('data.materials')->hiddenLabel()->schema([
                                    TextInput::make('name')->required(),
                                    Fields::image('image', 'Image (portrait)', 'pages'),
                                    TagsInput::make('tips')->label('Care tips')->reorderable()->columnSpanFull(),
                                ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state) => $state['name'] ?? null),
                            ]),

                            // ---- Gift cards ----
                            Section::make('How it works')->visible($is('gift-cards'))->schema([
                                Repeater::make('data.steps')->hiddenLabel()->schema([
                                    TextInput::make('title')->required(),
                                    Textarea::make('text')->rows(2)->required(),
                                ])->columns(2)->maxItems(4)->reorderable(),
                            ]),

                            // ---- Careers ----
                            Section::make('Open roles')->visible($is('careers'))->schema([
                                Repeater::make('data.roles')->hiddenLabel()->schema([
                                    TextInput::make('title')->required(),
                                    TextInput::make('location'),
                                    TextInput::make('type')->placeholder('Full-time'),
                                    TextInput::make('apply_url')->label('Apply link (optional)')->url(),
                                    Textarea::make('summary')->rows(3)->columnSpanFull(),
                                ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state) => $state['title'] ?? null),
                                TagsInput::make('data.perks')->label('Perks')->reorderable(),
                            ]),

                            // ---- Returns CTA ----
                            Section::make('Call to action')->visible(fn (Get $get) => in_array($get('template'), ['legal', 'default'], true))->columns(2)->schema([
                                TextInput::make('data.cta_label')->label('Button label')->placeholder('Start a return'),
                                TextInput::make('data.cta_url')->label('Button link')->placeholder('/account/orders'),
                            ]),

                            Section::make('No extra blocks')
                                ->visible(fn (Get $get) => in_array($get('template'), ['faq', 'stores', 'cookies'], true))
                                ->description('This template pulls its content from FAQs / Boutiques / cookie preferences automatically.')
                                ->schema([]),
                        ]),

                    Fields::seoTab(),
                ]),
            ]);
    }
}
