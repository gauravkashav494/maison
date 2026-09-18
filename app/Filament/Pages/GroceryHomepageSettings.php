<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use App\Models\Category;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Homepage content for the Indian Grocery template: hero banners, promo tiles,
 * section order/visibility, product rails, wide banner, promises and app block.
 */
class GroceryHomepageSettings extends Page
{
    use HasSettingsForm;

    public const SECTIONS = [
        'categories' => 'Shop by category',
        'promos' => 'Promo tiles',
        'deals' => 'Deals of the day',
        'fresh' => 'Fresh from the farm',
        'banner' => 'Wide banner',
        'bestsellers' => 'Bestsellers',
        'new' => 'New in store',
        'brands' => 'Top brands',
        'promises' => 'Why shop with us',
        'app' => 'App promotion',
    ];

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 21;

    protected static ?string $title = 'Grocery — Homepage';

    protected static ?string $navigationLabel = 'Grocery · Homepage';

    protected function settingGroups(): array
    {
        return ['grocery_home'];
    }

    protected function defaultsFor(string $group): array
    {
        return app(TemplateManager::class)->get('grocery')->defaults()['home'] ?? [];
    }

    public function form(Schema $schema): Schema
    {
        $g = 'grocery_home';
        $categoryOptions = fn () => Category::query()->whereNull('parent_id')->orderBy('name')->pluck('name', 'slug')->all();

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Grocery homepage')->persistTabInQueryString()->tabs([
                    Tab::make('Hero banners')->icon('heroicon-o-photo')->schema([
                        Repeater::make("$g.hero_banners")
                            ->label('Slides')
                            ->helperText('Shown as a carousel at the top of the homepage. Recommended image 1400×700.')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('eyebrow')->label('Eyebrow'),
                                    Select::make('theme')->options(['green' => 'Green', 'saffron' => 'Saffron', 'berry' => 'Berry', 'sky' => 'Sky', 'dark' => 'Dark'])->default('green')->native(false),
                                ]),
                                TextInput::make('heading')->required(),
                                Textarea::make('text')->rows(2),
                                Grid::make(2)->schema([
                                    TextInput::make('cta_label')->label('Button label'),
                                    TextInput::make('cta_url')->label('Button link'),
                                ]),
                                Fields::image('image', 'Image', 'grocery'),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['heading'] ?? null)
                            ->addActionLabel('Add slide')
                            ->maxItems(6),
                    ]),

                    Tab::make('Promo tiles')->icon('heroicon-o-gift')->schema([
                        Repeater::make("$g.promo_tiles")
                            ->label('Tiles')
                            ->helperText('Three tiles work best. Square images, 600×600.')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('title')->required(),
                                    TextInput::make('text')->label('Subtitle'),
                                    TextInput::make('cta_label')->label('Link label'),
                                    TextInput::make('url')->label('Link'),
                                ]),
                                Grid::make(2)->schema([
                                    Fields::image('image', 'Image', 'grocery'),
                                    ColorPicker::make('color')->label('Background colour'),
                                ]),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->addActionLabel('Add tile')
                            ->maxItems(6),
                    ]),

                    Tab::make('Sections')->icon('heroicon-o-bars-3-bottom-left')->schema([
                        Repeater::make("$g.sections")
                            ->label('Order & visibility')
                            ->helperText('Drag to reorder the homepage. Switch a section off to hide it without losing its content.')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('key')->label('Section')->options(self::SECTIONS)->required()->native(false)->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                    Toggle::make('enabled')->label('Show')->default(true)->inline(false),
                                ]),
                            ])
                            ->reorderable()
                            ->itemLabel(fn (array $state): ?string => self::SECTIONS[$state['key'] ?? ''] ?? null)
                            ->addActionLabel('Add section')
                            ->maxItems(count(self::SECTIONS)),
                    ]),

                    Tab::make('Product rails')->icon('heroicon-o-squares-2x2')->schema([
                        Section::make('Shop by category')->columns(2)->schema([
                            TextInput::make("$g.categories_heading")->label('Heading'),
                            TextInput::make("$g.categories_limit")->label('Max categories')->numeric()->minValue(4)->maxValue(24),
                        ]),
                        Section::make('Deals of the day')->description('Products with a compare-at price higher than the price.')->columns(3)->schema([
                            TextInput::make("$g.deals_heading")->label('Heading'),
                            TextInput::make("$g.deals_text")->label('Subheading')->columnSpan(2),
                            TextInput::make("$g.deals_limit")->label('Max products')->numeric()->minValue(4)->maxValue(20),
                        ]),
                        Section::make('Fresh from the farm')->description('Products from one category.')->columns(3)->schema([
                            TextInput::make("$g.fresh_heading")->label('Heading'),
                            TextInput::make("$g.fresh_text")->label('Subheading')->columnSpan(2),
                            Select::make("$g.fresh_category_slug")->label('Category')->options($categoryOptions)->searchable()->native(false),
                            TextInput::make("$g.fresh_limit")->label('Max products')->numeric()->minValue(4)->maxValue(20),
                        ]),
                        Section::make('Bestsellers')->description('Products flagged "Best seller".')->columns(3)->schema([
                            TextInput::make("$g.bestsellers_heading")->label('Heading'),
                            TextInput::make("$g.bestsellers_text")->label('Subheading')->columnSpan(2),
                            TextInput::make("$g.bestsellers_limit")->label('Max products')->numeric()->minValue(4)->maxValue(20),
                        ]),
                        Section::make('New in store')->description('Products flagged "New arrival".')->columns(3)->schema([
                            TextInput::make("$g.new_heading")->label('Heading'),
                            TextInput::make("$g.new_text")->label('Subheading')->columnSpan(2),
                            TextInput::make("$g.new_limit")->label('Max products')->numeric()->minValue(4)->maxValue(20),
                        ]),
                        Section::make('Top brands')->description('Brands are read from the products in the grocery catalogue.')->schema([
                            TextInput::make("$g.brands_heading")->label('Heading'),
                        ]),
                    ]),

                    Tab::make('Wide banner')->icon('heroicon-o-rectangle-group')->schema([
                        TextInput::make("$g.banner_heading")->label('Heading'),
                        Textarea::make("$g.banner_text")->label('Text')->rows(2),
                        Grid::make(2)->schema([
                            TextInput::make("$g.banner_cta_label")->label('Button label'),
                            TextInput::make("$g.banner_cta_url")->label('Button link'),
                        ]),
                        Fields::image("$g.banner_image", 'Image (landscape, 1600×700)', 'grocery'),
                    ]),

                    Tab::make('Promises & app')->icon('heroicon-o-sparkles')->schema([
                        Repeater::make("$g.promises")
                            ->label('Why shop with us')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('icon')->options(['bolt' => 'Lightning', 'leaf' => 'Leaf', 'tag' => 'Price tag', 'shield' => 'Shield', 'truck' => 'Truck', 'rotate' => 'Returns', 'clock' => 'Clock', 'star' => 'Star'])->native(false)->required(),
                                    TextInput::make('title')->required()->columnSpan(2),
                                ]),
                                TextInput::make('text'),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->maxItems(6),
                        Section::make('App promotion block')->columns(2)->schema([
                            TextInput::make("$g.app_heading")->label('Heading'),
                            TextInput::make("$g.app_text")->label('Text'),
                            Fields::image("$g.app_image", 'Image (portrait)', 'grocery')->columnSpan(2),
                        ]),
                        Section::make('Newsletter')->columns(2)->schema([
                            TextInput::make("$g.newsletter_heading")->label('Heading'),
                            TextInput::make("$g.newsletter_text")->label('Text'),
                        ]),
                    ]),
                ]),
            ]);
    }
}
