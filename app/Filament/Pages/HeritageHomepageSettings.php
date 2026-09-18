<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use App\Models\Collection;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Homepage content for the Heritage Grocery template. */
class HeritageHomepageSettings extends Page
{
    use HasSettingsForm;

    public const SECTIONS = [
        'categories' => 'Shop by category',
        'featured' => 'Featured products',
        'offer' => 'Special offers block',
        'bestsellers' => 'Best sellers',
        'story' => 'Heritage / brand story',
        'needs' => 'Shop by need',
        'brands' => 'Popular brands',
        'testimonials' => 'Customer reviews',
        'journal' => 'Recipes & stories',
        'trust' => 'Trust / benefits',
    ];

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 31;

    protected static ?string $title = 'Heritage — Homepage';

    protected static ?string $navigationLabel = 'Heritage · Homepage';

    protected function settingGroups(): array
    {
        return ['heritage_home'];
    }

    protected function defaultsFor(string $group): array
    {
        return app(TemplateManager::class)->get('heritage')->defaults()['home'] ?? [];
    }

    public function form(Schema $schema): Schema
    {
        $g = 'heritage_home';
        $collections = fn () => Collection::query()->where('template', 'heritage')->orWhereNull('template')->orderBy('name')->pluck('name', 'slug')->all();

        return $schema->statePath('data')->components([
            Tabs::make('Heritage homepage')->persistTabInQueryString()->tabs([
                Tab::make('Hero banners')->icon('heroicon-o-photo')->schema([
                    Repeater::make("$g.hero_slides")->label('Slides')->helperText('Full-width carousel. Images 1600×1000 work best.')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('eyebrow')->label('Eyebrow'),
                                Select::make('align')->label('Text position')->options(['left' => 'Left', 'center' => 'Centre'])->default('left')->native(false),
                            ]),
                            TextInput::make('heading')->required(),
                            Textarea::make('text')->rows(2),
                            Grid::make(2)->schema([
                                TextInput::make('cta_label')->label('Primary button'),
                                TextInput::make('cta_url')->label('Primary link'),
                                TextInput::make('secondary_label')->label('Secondary link label'),
                                TextInput::make('secondary_url')->label('Secondary link'),
                            ]),
                            Fields::image('image', 'Image', 'heritage'),
                        ])->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['heading'] ?? null)->addActionLabel('Add slide')->maxItems(6),
                ]),

                Tab::make('Sections')->icon('heroicon-o-bars-3-bottom-left')->schema([
                    Repeater::make("$g.sections")->label('Order & visibility')->helperText('Drag to reorder. Switch a section off to hide it without losing its content.')
                        ->schema([Grid::make(2)->schema([
                            Select::make('key')->label('Section')->options(self::SECTIONS)->required()->native(false)->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            Toggle::make('enabled')->label('Show')->default(true)->inline(false),
                        ])])->reorderable()->itemLabel(fn (array $state): ?string => self::SECTIONS[$state['key'] ?? ''] ?? null)->maxItems(count(self::SECTIONS)),
                ]),

                Tab::make('Products')->icon('heroicon-o-squares-2x2')->schema([
                    Section::make('Shop by category')->columns(3)->schema([
                        TextInput::make("$g.categories_eyebrow")->label('Eyebrow'),
                        TextInput::make("$g.categories_heading")->label('Heading')->columnSpan(2),
                        TextInput::make("$g.categories_limit")->label('Max categories')->numeric()->minValue(4)->maxValue(16),
                    ]),
                    Section::make('Featured products')->columns(3)->schema([
                        TextInput::make("$g.featured_eyebrow")->label('Eyebrow'),
                        TextInput::make("$g.featured_heading")->label('Heading')->columnSpan(2),
                        TextInput::make("$g.featured_text")->label('Subheading')->columnSpan(3),
                        Select::make("$g.featured_source")->label('Source')->options(['new' => 'Products flagged “New arrival”', 'collection' => 'A collection'])->native(false)->live(),
                        Select::make("$g.featured_collection_slug")->label('Collection')->options($collections)->searchable()->native(false)->visible(fn (Get $get) => $get("$g.featured_source") === 'collection'),
                        TextInput::make("$g.featured_limit")->label('Max products')->numeric()->minValue(4)->maxValue(16),
                    ]),
                    Section::make('Best sellers')->description('Products flagged “Best seller”.')->columns(3)->schema([
                        TextInput::make("$g.bestsellers_eyebrow")->label('Eyebrow'),
                        TextInput::make("$g.bestsellers_heading")->label('Heading'),
                        TextInput::make("$g.bestsellers_limit")->label('Max products')->numeric()->minValue(4)->maxValue(16),
                    ]),
                    Section::make('Shop by need')->description('Shows the template’s collections (Everyday essentials, Festive cooking…). Manage them under Catalogue → Collections.')->columns(3)->schema([
                        TextInput::make("$g.needs_eyebrow")->label('Eyebrow'),
                        TextInput::make("$g.needs_heading")->label('Heading'),
                        TextInput::make("$g.needs_limit")->label('Max tiles')->numeric()->minValue(3)->maxValue(12),
                    ]),
                ]),

                Tab::make('Offer & story')->icon('heroicon-o-gift')->schema([
                    Section::make('Special offers block')->columns(2)->schema([
                        TextInput::make("$g.offer_eyebrow")->label('Eyebrow'),
                        TextInput::make("$g.offer_badge")->label('Badge (e.g. Up to 30% off)'),
                        TextInput::make("$g.offer_heading")->label('Heading')->columnSpan(2),
                        Textarea::make("$g.offer_text")->label('Text')->rows(2)->columnSpan(2),
                        TextInput::make("$g.offer_cta_label")->label('Button label'),
                        TextInput::make("$g.offer_cta_url")->label('Button link'),
                        Fields::image("$g.offer_image", 'Image (square)', 'heritage')->columnSpan(2),
                    ]),
                    Section::make('Heritage / brand story')->columns(2)->schema([
                        TextInput::make("$g.story_eyebrow")->label('Eyebrow'),
                        TextInput::make("$g.story_heading")->label('Heading'),
                        Textarea::make("$g.story_text")->label('Text')->rows(5)->helperText('Blank line = new paragraph.')->columnSpan(2),
                        TextInput::make("$g.story_cta_label")->label('Button label'),
                        TextInput::make("$g.story_cta_url")->label('Button link'),
                        Fields::image("$g.story_image", 'Main image (portrait)', 'heritage'),
                        Fields::image("$g.story_image_secondary", 'Secondary image (landscape)', 'heritage'),
                        Repeater::make("$g.story_stats")->label('Statistics')->schema([Grid::make(2)->schema([TextInput::make('value')->required(), TextInput::make('label')->required()])])->reorderable()->maxItems(4)->columnSpan(2),
                    ]),
                ]),

                Tab::make('Brands & reviews')->icon('heroicon-o-star')->schema([
                    Section::make('Popular brands')->description('Leave the list empty to show the brands found on your products automatically.')->schema([
                        Grid::make(2)->schema([
                            TextInput::make("$g.brands_eyebrow")->label('Eyebrow'),
                            TextInput::make("$g.brands_heading")->label('Heading'),
                        ]),
                        Repeater::make("$g.brands")->label('Brands')->schema([
                            Grid::make(2)->schema([TextInput::make('name')->required(), TextInput::make('url')->label('Link')]),
                            Fields::image('logo', 'Logo (optional)', 'heritage'),
                        ])->reorderable()->collapsible()->defaultItems(0)->itemLabel(fn (array $state): ?string => $state['name'] ?? null)->addActionLabel('Add brand'),
                    ]),
                    Section::make('Customer reviews')->schema([
                        Grid::make(2)->schema([
                            TextInput::make("$g.testimonials_eyebrow")->label('Eyebrow'),
                            TextInput::make("$g.testimonials_heading")->label('Heading'),
                        ]),
                        Repeater::make("$g.testimonials")->label('Testimonials')->schema([
                            Grid::make(3)->schema([
                                TextInput::make('name')->required(),
                                TextInput::make('location'),
                                Select::make('rating')->options([5 => '5 stars', 4 => '4 stars', 3 => '3 stars'])->default(5)->native(false),
                            ]),
                            Textarea::make('text')->label('Review')->rows(3)->required(),
                            TextInput::make('product')->label('Product (optional)'),
                        ])->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['name'] ?? null)->addActionLabel('Add review')->maxItems(9),
                    ]),
                ]),

                Tab::make('Trust & newsletter')->icon('heroicon-o-shield-check')->schema([
                    Repeater::make("$g.trust_items")->label('Trust / benefits')->schema([
                        Grid::make(3)->schema([
                            Select::make('icon')->options(['badge' => 'Quality badge', 'lock' => 'Lock', 'truck' => 'Truck', 'rotate' => 'Returns', 'leaf' => 'Leaf', 'star' => 'Star', 'shield' => 'Shield', 'phone' => 'Phone'])->native(false)->required(),
                            TextInput::make('title')->required()->columnSpan(2),
                        ]),
                        TextInput::make('text'),
                    ])->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['title'] ?? null)->maxItems(6),
                    Section::make('Journal')->columns(2)->schema([
                        TextInput::make("$g.journal_eyebrow")->label('Eyebrow'),
                        TextInput::make("$g.journal_heading")->label('Heading'),
                    ]),
                    Section::make('Newsletter')->columns(1)->schema([
                        TextInput::make("$g.newsletter_eyebrow")->label('Eyebrow'),
                        TextInput::make("$g.newsletter_heading")->label('Heading'),
                        TextInput::make("$g.newsletter_text")->label('Text'),
                    ]),
                ]),
            ]),
        ]);
    }
}
