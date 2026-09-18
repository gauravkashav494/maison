<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use App\Models\Collection;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
        'strip' => 'Promise strip (under the hero)',
        'featured' => 'Featured collection carousel',
        'combos' => 'Combos carousel',
        'bestsellers' => 'Best sellers carousel',
        'video' => 'Video banner',
        'needs' => 'Shop by need (tabs)',
        'new' => 'New arrivals carousel',
        'certifications' => 'Certifications',
        'trust' => 'Benefits banner',
        'categories' => 'Range of categories (tabs)',
        'offer' => 'Gifting / offer banner',
        'values' => 'Values icon strip',
        'journal' => 'Blogs',
        'testimonials' => 'Happy customers',
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
        $collections = fn () => Collection::query()->where(fn ($q) => $q->where('template', 'heritage')->orWhereNull('template'))->orderBy('name')->pluck('name', 'slug')->all();
        $icons = ['badge' => 'Quality badge', 'lock' => 'Lock', 'truck' => 'Truck', 'rotate' => 'Returns', 'leaf' => 'Leaf', 'star' => 'Star', 'shield' => 'Shield', 'diamond' => 'Diamond', 'check' => 'Check', 'phone' => 'Phone', 'gift' => 'Gift'];

        return $schema->statePath('data')->components([
            Tabs::make('Heritage homepage')->persistTabInQueryString()->tabs([
                Tab::make('Hero banners')->icon('heroicon-o-photo')->schema([
                    Repeater::make("$g.hero_slides")->label('Slides')->helperText('Rounded full-width carousel: text on the left, image on the right, four badge lines under the text.')
                        ->schema([
                            TextInput::make('eyebrow')->label('Eyebrow'),
                            TextInput::make('heading')->required(),
                            Textarea::make('text')->rows(2),
                            Grid::make(2)->schema([TextInput::make('cta_label')->label('Button label'), TextInput::make('cta_url')->label('Button link')]),
                            TagsInput::make('badges')->label('Badge lines')->helperText('Up to four short promises shown under the text.'),
                            Fields::image('image', 'Image (right side, 1200×1000)', 'heritage'),
                        ])->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['heading'] ?? null)->addActionLabel('Add slide')->maxItems(8),
                ]),

                Tab::make('Sections')->icon('heroicon-o-bars-3-bottom-left')->schema([
                    Repeater::make("$g.sections")->label('Order & visibility')->helperText('Drag to reorder the homepage. Switch a section off to hide it without losing its content.')
                        ->schema([Grid::make(2)->schema([
                            Select::make('key')->label('Section')->options(self::SECTIONS)->required()->native(false)->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            Toggle::make('enabled')->label('Show')->default(true)->inline(false),
                        ])])->reorderable()->itemLabel(fn (array $state): ?string => self::SECTIONS[$state['key'] ?? ''] ?? null)->maxItems(count(self::SECTIONS)),
                    Section::make('Promise strip')->columns(2)->schema([
                        Textarea::make("$g.strip_text")->label('Text')->rows(2)->columnSpan(2),
                        Fields::image("$g.strip_image", 'Image (small, right side)', 'heritage'),
                    ]),
                ]),

                Tab::make('Product carousels')->icon('heroicon-o-squares-2x2')->schema([
                    Section::make('Featured collection')->columns(3)->schema([
                        TextInput::make("$g.featured_heading")->label('Heading'),
                        Select::make("$g.featured_source")->label('Source')->options(['collection' => 'A collection', 'new' => 'Products flagged “New arrival”'])->native(false)->live(),
                        Select::make("$g.featured_collection_slug")->label('Collection')->options($collections)->searchable()->native(false)->visible(fn (Get $get) => $get("$g.featured_source") !== 'new'),
                        TextInput::make("$g.featured_limit")->label('Max products')->numeric()->minValue(4)->maxValue(20),
                    ]),
                    Section::make('Combos')->columns(3)->schema([
                        TextInput::make("$g.combos_heading")->label('Heading'),
                        Select::make("$g.combos_collection_slug")->label('Collection')->options($collections)->searchable()->native(false),
                        TextInput::make("$g.combos_limit")->label('Max products')->numeric()->minValue(4)->maxValue(20),
                    ]),
                    Section::make('Best sellers')->description('Products flagged “Best seller”.')->columns(2)->schema([
                        TextInput::make("$g.bestsellers_heading")->label('Heading'),
                        TextInput::make("$g.bestsellers_limit")->label('Max products')->numeric()->minValue(4)->maxValue(20),
                    ]),
                    Section::make('New arrivals')->description('Products flagged “New arrival”.')->columns(2)->schema([
                        TextInput::make("$g.new_heading")->label('Heading'),
                        TextInput::make("$g.new_limit")->label('Max products')->numeric()->minValue(4)->maxValue(20),
                    ]),
                    Section::make('Shop by need (tabs)')->description('One tab per collection with its products. Manage collections under Catalogue → Collections.')->columns(2)->schema([
                        TextInput::make("$g.needs_heading")->label('Heading'),
                        TextInput::make("$g.needs_limit")->label('Max tabs')->numeric()->minValue(3)->maxValue(12),
                    ]),
                    Section::make('Range of categories (tabs)')->columns(2)->schema([
                        TextInput::make("$g.categories_heading")->label('Heading'),
                        TextInput::make("$g.categories_limit")->label('Max tabs')->numeric()->minValue(3)->maxValue(16),
                    ]),
                ]),

                Tab::make('Banners')->icon('heroicon-o-rectangle-group')->schema([
                    Section::make('Video banner')->columns(2)->schema([
                        TextInput::make("$g.video_url")->label('YouTube or MP4 URL')->helperText('Leave empty to hide the section.'),
                        Fields::image("$g.video_poster", 'Poster image (1600×800)', 'heritage'),
                    ]),
                    Section::make('Gifting / offer banner')->columns(2)->schema([
                        TextInput::make("$g.offer_heading")->label('Heading'),
                        TextInput::make("$g.offer_badge")->label('Badge'),
                        Textarea::make("$g.offer_text")->label('Text')->rows(2)->columnSpan(2),
                        TextInput::make("$g.offer_cta_label")->label('Button label'),
                        TextInput::make("$g.offer_cta_url")->label('Button link'),
                        Fields::image("$g.offer_image", 'Image (1600×800)', 'heritage')->columnSpan(2),
                    ]),
                    Section::make('Farmers / brand story (product pages)')->columns(2)->schema([
                        TextInput::make("$g.story_heading")->label('Heading'),
                        Textarea::make("$g.story_text")->label('Text')->rows(4)->columnSpan(2),
                        TextInput::make("$g.story_cta_label")->label('Button label'),
                        TextInput::make("$g.story_cta_url")->label('Button link'),
                        Fields::image("$g.story_image", 'Image (portrait)', 'heritage'),
                        Repeater::make("$g.story_stats")->label('Statistics strip')->schema([Grid::make(2)->schema([TextInput::make('value')->required(), TextInput::make('label')->required()])])->reorderable()->maxItems(5),
                    ]),
                ]),

                Tab::make('Trust & certifications')->icon('heroicon-o-shield-check')->schema([
                    Section::make('Certifications')->schema([
                        TextInput::make("$g.certifications_heading")->label('Heading'),
                        Repeater::make("$g.certifications")->label('Badges')->schema([Grid::make(2)->schema([TextInput::make('label')->required(), Fields::image('logo', 'Logo (optional)', 'heritage')])])->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['label'] ?? null)->maxItems(12),
                    ]),
                    Section::make('Benefits banner')->schema([
                        TextInput::make("$g.trust_heading")->label('Heading'),
                        Fields::image("$g.trust_image", 'Image (left side)', 'heritage'),
                        Repeater::make("$g.trust_items")->label('Six benefits')->schema([Grid::make(3)->schema([Select::make('icon')->options($icons)->native(false)->required(), TextInput::make('title')->required(), TextInput::make('text')])])->reorderable()->maxItems(6),
                    ]),
                    Section::make('Values icon strip')->schema([
                        Repeater::make("$g.values_strip")->label('Items')->schema([Grid::make(2)->schema([Select::make('icon')->options($icons)->native(false)->required(), TextInput::make('label')->required()])])->reorderable()->maxItems(6),
                    ]),
                ]),

                Tab::make('Blogs, reviews & newsletter')->icon('heroicon-o-star')->schema([
                    TextInput::make("$g.journal_heading")->label('Blogs heading'),
                    Section::make('Happy customers')->schema([
                        TextInput::make("$g.testimonials_heading")->label('Heading'),
                        Fields::image("$g.testimonials_image", 'Image card (portrait)', 'heritage'),
                        Repeater::make("$g.testimonials")->label('Reviews')->schema([
                            Grid::make(3)->schema([TextInput::make('name')->required(), TextInput::make('location'), Select::make('rating')->options([5 => '5 stars', 4 => '4 stars', 3 => '3 stars'])->default(5)->native(false)]),
                            Textarea::make('text')->label('Review')->rows(2)->required(),
                            TextInput::make('product')->label('Product'),
                        ])->reorderable()->collapsible()->itemLabel(fn (array $state): ?string => $state['name'] ?? null)->maxItems(12),
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
