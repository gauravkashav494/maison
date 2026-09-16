<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use App\Models\Collection;
use App\Models\Product;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class HomepageSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 31;

    protected static ?string $title = 'Homepage';

    protected function settingGroups(): array
    {
        return ['home'];
    }

    public function form(Schema $schema): Schema
    {
        $italicHint = 'Wrap a word in *asterisks* to set it in italic serif.';

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Homepage')->persistTabInQueryString()->tabs([
                    Tab::make('Hero')->icon('heroicon-o-photo')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('home.hero_eyebrow')->label('Eyebrow'),
                            TextInput::make('home.hero_eyebrow_short')->label('Eyebrow (mobile)'),
                        ]),
                        Textarea::make('home.hero_heading')->label('Headline')->rows(3)->helperText('One line per row. '.$italicHint),
                        Textarea::make('home.hero_text')->label('Statement')->rows(2),
                        Grid::make(2)->schema([
                            TextInput::make('home.hero_primary_label')->label('Primary button label'),
                            TextInput::make('home.hero_primary_url')->label('Primary button link'),
                            TextInput::make('home.hero_secondary_label')->label('Secondary link label'),
                            TextInput::make('home.hero_secondary_url')->label('Secondary link'),
                        ]),
                        Fields::image('home.hero_image', 'Campaign image (portrait, min 1600×2000)', 'home'),
                        Grid::make(3)->schema([
                            TextInput::make('home.hero_image_alt')->label('Image alt text'),
                            TextInput::make('home.hero_caption')->label('Caption (bottom left)'),
                            TextInput::make('home.hero_look_title')->label('Look title'),
                        ]),
                        TextInput::make('home.hero_look_text')->label('Look description'),
                        TagsInput::make('home.ticker_items')->label('Ticker words')->reorderable(),
                    ]),

                    Tab::make('Categories & Arrivals')->icon('heroicon-o-squares-2x2')->schema([
                        Section::make('Featured categories')->description('Categories are pulled from the catalogue (ordered by sort order, "Show on homepage grid" enabled).')->schema([
                            TextInput::make('home.categories_eyebrow')->label('Eyebrow'),
                            TextInput::make('home.categories_heading')->label('Heading'),
                            Textarea::make('home.categories_text')->label('Description')->rows(2),
                        ]),
                        Section::make('New arrivals')->description('Products flagged "New arrival".')->schema([
                            Grid::make(3)->schema([
                                TextInput::make('home.arrivals_eyebrow')->label('Eyebrow'),
                                TextInput::make('home.arrivals_heading')->label('Heading'),
                                TextInput::make('home.arrivals_limit')->numeric()->minValue(4)->maxValue(16)->label('Products shown'),
                            ]),
                            Textarea::make('home.arrivals_text')->label('Description')->rows(2),
                        ]),
                    ]),

                    Tab::make('Editorial')->icon('heroicon-o-sparkles')->schema([
                        TextInput::make('home.editorial_eyebrow')->label('Eyebrow'),
                        TextInput::make('home.editorial_heading')->label('Heading')->helperText($italicHint),
                        Textarea::make('home.editorial_text')->label('Body')->rows(4),
                        Grid::make(2)->schema([
                            TextInput::make('home.editorial_cta_label')->label('Button label'),
                            TextInput::make('home.editorial_cta_url')->label('Button link'),
                        ]),
                        Grid::make(2)->schema([
                            Fields::image('home.editorial_image', 'Primary image (portrait)', 'home'),
                            Fields::image('home.editorial_image_secondary', 'Offset image (portrait)', 'home'),
                        ]),
                        TextInput::make('home.editorial_caption')->label('Vertical caption'),
                        Repeater::make('home.editorial_stats')->label('Statistics')->schema([
                            TextInput::make('value')->required(),
                            TextInput::make('label')->required(),
                        ])->columns(2)->maxItems(3)->reorderable(),
                    ]),

                    Tab::make('Featured collection')->icon('heroicon-o-rectangle-stack')->schema([
                        Select::make('home.featured_collection_id')
                            ->label('Collection')
                            ->options(fn () => Collection::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Uses the collection’s hero/card image, name and description.'),
                        TextInput::make('home.featured_eyebrow')->label('Eyebrow'),
                        Textarea::make('home.featured_text_extra')->label('Extra sentence after the description')->rows(2),
                        Grid::make(2)->schema([
                            TextInput::make('home.featured_secondary_label')->label('Secondary button label'),
                            TextInput::make('home.featured_secondary_url')->label('Secondary button link'),
                        ]),
                        Section::make('Best sellers')->description('Products flagged "Best seller".')->schema([
                            Grid::make(2)->schema([
                                TextInput::make('home.bestsellers_eyebrow')->label('Eyebrow'),
                                TextInput::make('home.bestsellers_heading')->label('Heading'),
                            ]),
                            Textarea::make('home.bestsellers_text')->label('Description')->rows(2),
                        ]),
                    ]),

                    Tab::make('Fragrance spotlight')->icon('heroicon-o-beaker')->schema([
                        Select::make('home.fragrance_product_id')
                            ->label('Product')
                            ->options(fn () => Product::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                        Grid::make(2)->schema([
                            TextInput::make('home.fragrance_eyebrow')->label('Eyebrow'),
                            TextInput::make('home.fragrance_heading')->label('Heading')->helperText($italicHint),
                            TextInput::make('home.fragrance_subheading')->label('Sub-heading'),
                            TextInput::make('home.fragrance_size')->label('Size added by the "Add" link')->placeholder('100ml'),
                        ]),
                        Textarea::make('home.fragrance_text')->label('Body')->rows(4),
                        Fields::image('home.fragrance_image', 'Atmosphere image (portrait)', 'home'),
                        TextInput::make('home.fragrance_caption')->label('Image caption'),
                        Repeater::make('home.fragrance_notes')->label('Notes')->schema([
                            TextInput::make('label')->required()->placeholder('Top'),
                            TextInput::make('value')->required()->placeholder('Pink pepper, bergamot'),
                        ])->columns(2)->reorderable(),
                    ]),

                    Tab::make('Journal, promises & newsletter')->icon('heroicon-o-newspaper')->schema([
                        Section::make('Journal')->schema([
                            Grid::make(2)->schema([
                                TextInput::make('home.journal_eyebrow')->label('Eyebrow'),
                                TextInput::make('home.journal_heading')->label('Heading'),
                            ]),
                        ]),
                        Section::make('Brand promises')->schema([
                            Repeater::make('home.promises')->hiddenLabel()->schema([
                                Select::make('icon')->options([
                                    'badge-check' => 'Badge check', 'gem' => 'Gem', 'lock' => 'Lock', 'truck' => 'Truck', 'rotate' => 'Returns', 'leaf' => 'Leaf', 'globe' => 'Globe', 'gift' => 'Gift',
                                ])->required(),
                                TextInput::make('title')->required(),
                                Textarea::make('text')->rows(2)->required()->columnSpanFull(),
                            ])->columns(2)->maxItems(5)->reorderable()->itemLabel(fn (array $state) => $state['title'] ?? null),
                        ]),
                        Section::make('Newsletter')->schema([
                            Grid::make(2)->schema([
                                TextInput::make('home.newsletter_eyebrow')->label('Eyebrow'),
                                TextInput::make('home.newsletter_heading')->label('Heading')->helperText($italicHint),
                            ]),
                            Textarea::make('home.newsletter_text')->label('Description')->rows(2),
                        ]),
                    ]),
                ]),
            ]);
    }
}
