<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Homepage content for the Plumbing template: hero, section order/visibility,
 * project cards, deals/featured/bestseller rails, promo banners, brands and trust points.
 */
class PlumbingHomepageSettings extends Page
{
    use HasSettingsForm;

    public const SECTIONS = [
        'categories' => 'Shop by category',
        'deals' => 'Today’s plumbing deals',
        'projects' => 'Shop for your project',
        'featured' => 'Featured products',
        'promos' => 'Promo banners',
        'bestsellers' => 'Bestsellers',
        'bulk' => 'Bulk quote band',
        'brands' => 'Trusted brands',
        'new' => 'New arrivals',
        'why' => 'Why choose us',
        'faq' => 'FAQ',
    ];

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 41;

    protected static ?string $title = 'Plumbing — Homepage';

    protected static ?string $navigationLabel = 'Plumbing · Homepage';

    protected function settingGroups(): array
    {
        return ['plumbing_home'];
    }

    protected function defaultsFor(string $group): array
    {
        return app(TemplateManager::class)->get('plumbing')->defaults()['home'] ?? [];
    }

    public function form(Schema $schema): Schema
    {
        $g = 'plumbing_home';
        $headingText = fn (string $key, string $label) => Section::make($label)->columns(2)->schema([
            TextInput::make("$g.{$key}_heading")->label('Heading'),
            TextInput::make("$g.{$key}_text")->label('Sub-heading'),
        ]);

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Plumbing homepage')->persistTabInQueryString()->tabs([
                    Tab::make('Hero')->icon('heroicon-o-photo')->schema([
                        Section::make('Copy')->columns(2)->schema([
                            TextInput::make("$g.hero_eyebrow")->label('Eyebrow')->columnSpan(2),
                            TextInput::make("$g.hero_heading")->label('Heading')->columnSpan(2),
                            Textarea::make("$g.hero_text")->label('Text')->rows(2)->columnSpan(2),
                            TextInput::make("$g.hero_primary_label")->label('Primary button'),
                            TextInput::make("$g.hero_primary_url")->label('Primary link'),
                            TextInput::make("$g.hero_secondary_label")->label('Secondary button'),
                            TextInput::make("$g.hero_secondary_url")->label('Secondary link')->helperText('#categories scrolls to the category grid.'),
                        ]),
                        Section::make('Visual')->columns(2)->schema([
                            Fields::image("$g.hero_image", 'Main image (portrait, 1200×1000)', 'plumbing'),
                            TextInput::make("$g.hero_image_alt")->label('Image alt text'),
                            Repeater::make("$g.hero_stats")->label('Stats under the buttons')->schema([TextInput::make('value')->required(), TextInput::make('label')->required()])->columns(2)->maxItems(4)->columnSpan(2),
                            Repeater::make("$g.hero_tiles")->label('Small tiles beside the image')->schema([
                                TextInput::make('title')->required(), TextInput::make('text'), TextInput::make('url')->label('Link'), Fields::image('image', 'Image (600×600)', 'plumbing'),
                            ])->columns(2)->maxItems(2)->columnSpan(2)->collapsible()->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                        ]),
                    ]),

                    Tab::make('Sections')->icon('heroicon-o-bars-3-bottom-left')->schema([
                        Repeater::make("$g.sections")->label('Order & visibility')->helperText('Drag to reorder the homepage. Switch a section off to hide it without losing its content.')
                            ->schema([Grid::make(2)->schema([
                                Select::make('key')->label('Section')->options(self::SECTIONS)->required()->native(false)->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Toggle::make('enabled')->label('Visible')->default(true)->inline(false),
                            ])])
                            ->reorderable()->itemLabel(fn (array $state): ?string => self::SECTIONS[$state['key'] ?? ''] ?? null)->addActionLabel('Add section')->maxItems(count(self::SECTIONS)),
                    ]),

                    Tab::make('Categories & projects')->icon('heroicon-o-squares-2x2')->schema([
                        $headingText('categories', 'Shop by category')->schema([
                            TextInput::make("$g.categories_heading")->label('Heading'),
                            TextInput::make("$g.categories_text")->label('Sub-heading'),
                            TextInput::make("$g.categories_limit")->label('Categories shown')->numeric()->minValue(4)->maxValue(12),
                        ])->columns(3),
                        $headingText('projects', 'Shop for your project'),
                        Repeater::make("$g.projects")->label('Project cards')->schema([
                            TextInput::make('title')->required(), TextInput::make('url')->label('Link'), TextInput::make('text')->label('Text')->columnSpan(2), Fields::image('image', 'Image (800×600)', 'plumbing')->columnSpan(2),
                        ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['title'] ?? null)->maxItems(8),
                    ]),

                    Tab::make('Product rails')->icon('heroicon-o-rectangle-group')->schema([
                        Section::make('Today’s plumbing deals')->columns(3)->schema([
                            TextInput::make("$g.deals_heading")->label('Heading'), TextInput::make("$g.deals_text")->label('Sub-heading'),
                            TextInput::make("$g.deals_limit")->label('Products')->numeric()->minValue(4)->maxValue(12),
                        ]),
                        Section::make('Featured products')->columns(3)->schema([
                            TextInput::make("$g.featured_heading")->label('Heading'), TextInput::make("$g.featured_text")->label('Sub-heading'),
                            TextInput::make("$g.featured_limit")->label('Products')->numeric()->minValue(4)->maxValue(16),
                        ]),
                        Section::make('Bestsellers')->columns(3)->schema([
                            TextInput::make("$g.bestsellers_heading")->label('Heading'), TextInput::make("$g.bestsellers_text")->label('Sub-heading'),
                            TextInput::make("$g.bestsellers_limit")->label('Products')->numeric()->minValue(4)->maxValue(16),
                        ]),
                        Section::make('New arrivals')->columns(3)->schema([
                            TextInput::make("$g.new_heading")->label('Heading'), TextInput::make("$g.new_text")->label('Sub-heading'),
                            TextInput::make("$g.new_limit")->label('Products')->numeric()->minValue(4)->maxValue(16),
                        ]),
                    ]),

                    Tab::make('Banners & brands')->icon('heroicon-o-megaphone')->schema([
                        Repeater::make("$g.promos")->label('Promo banners')->helperText('Two side-by-side banners between the product rails.')->schema([
                            TextInput::make('eyebrow'), TextInput::make('heading')->required(), Textarea::make('text')->rows(2)->columnSpan(2),
                            TextInput::make('cta_label')->label('Button label'), TextInput::make('url')->label('Link'),
                            Select::make('tone')->options(['blue' => 'Blue', 'orange' => 'Orange', 'light' => 'Light'])->default('blue')->native(false),
                            Fields::image('image', 'Image (900×700)', 'plumbing'),
                        ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['heading'] ?? null)->maxItems(2),
                        $headingText('brands', 'Trusted brands')->description('Brands are read from the products’ brand field — no separate list to maintain.'),
                    ]),

                    Tab::make('Trust & FAQ')->icon('heroicon-o-shield-check')->schema([
                        Section::make('Why choose us')->schema([
                            TextInput::make("$g.why_heading")->label('Heading'),
                            Repeater::make("$g.why_items")->label('Points')->schema([
                                Select::make('icon')->options(['badge' => 'Badge', 'truck' => 'Truck', 'tag' => 'Tag', 'shield' => 'Shield', 'headset' => 'Headset', 'rotate' => 'Returns', 'check' => 'Check', 'bolt' => 'Bolt'])->native(false),
                                TextInput::make('title')->required(), TextInput::make('text')->columnSpan(2),
                            ])->columns(2)->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['title'] ?? null)->maxItems(6),
                        ]),
                        Section::make('FAQ section')->description('Shows the first six FAQs from Content → FAQs when the section is enabled.')->schema([
                            TextInput::make("$g.faq_heading")->label('Heading'),
                        ]),
                    ]),
                ]),
            ]);
    }
}
