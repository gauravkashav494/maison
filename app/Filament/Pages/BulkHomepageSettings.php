<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
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
 * Homepage content for the Bulk Order template: hero and its three steps, the order form's
 * heading, and the order/visibility of the sections below it. The form itself always shows.
 */
class BulkHomepageSettings extends Page
{
    use HasSettingsForm;

    public const SECTIONS = [
        'categories' => 'Shop by category',
        'featured' => 'Most ordered',
        'bulk' => 'Large-quantity quote band',
        'deals' => 'Today’s deals',
        'bestsellers' => 'Bestsellers',
        'new' => 'New arrivals',
        'brands' => 'Brands',
        'why' => 'Why choose us',
        'faq' => 'FAQ',
    ];

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 61;

    protected static ?string $title = 'Bulk Order — Homepage';

    protected static ?string $navigationLabel = 'Bulk Order · Homepage';

    protected function settingGroups(): array
    {
        return ['bulk_home'];
    }

    protected function defaultsFor(string $group): array
    {
        return app(TemplateManager::class)->get('bulk')->defaults()['home'] ?? [];
    }

    public function form(Schema $schema): Schema
    {
        $g = 'bulk_home';
        $rail = fn (string $key, string $label, int $max = 16) => Section::make($label)->columns(3)->schema([
            TextInput::make("$g.{$key}_heading")->label('Heading'), TextInput::make("$g.{$key}_text")->label('Sub-heading'),
            TextInput::make("$g.{$key}_limit")->label('Products')->numeric()->minValue(4)->maxValue($max),
        ]);

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Bulk Order homepage')->persistTabInQueryString()->tabs([
                    Tab::make('Hero & order form')->icon('heroicon-o-queue-list')->schema([
                        Section::make('Hero')->columns(2)->schema([
                            TextInput::make("$g.hero_eyebrow")->label('Eyebrow')->columnSpan(2),
                            TextInput::make("$g.hero_heading")->label('Heading')->columnSpan(2),
                            Textarea::make("$g.hero_text")->label('Text')->rows(2)->columnSpan(2),
                            TextInput::make("$g.hero_primary_label")->label('Primary button')->helperText('Scrolls to the order form.')->columnSpan(2),
                            TextInput::make("$g.hero_secondary_label")->label('Secondary button'),
                            TextInput::make("$g.hero_secondary_url")->label('Secondary link'),
                            Repeater::make("$g.hero_steps")->label('How it works (three steps)')->schema([
                                TextInput::make('title')->required(), TextInput::make('text'),
                            ])->columns(2)->reorderable()->maxItems(3)->columnSpan(2)->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                        ]),
                        Section::make('Order form')->description('Always shown directly under the hero.')->columns(2)->schema([
                            TextInput::make("$g.order_heading")->label('Heading'),
                            TextInput::make("$g.order_text")->label('Sub-heading'),
                        ]),
                    ]),

                    Tab::make('Sections')->icon('heroicon-o-bars-3-bottom-left')->schema([
                        Repeater::make("$g.sections")->label('Order & visibility')->helperText('Sections shown below the order form. Drag to reorder; switch one off to hide it without losing its content.')
                            ->schema([Grid::make(2)->schema([
                                Select::make('key')->label('Section')->options(self::SECTIONS)->required()->native(false)->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Toggle::make('enabled')->label('Visible')->default(true)->inline(false),
                            ])])
                            ->reorderable()->itemLabel(fn (array $state): ?string => self::SECTIONS[$state['key'] ?? ''] ?? null)->addActionLabel('Add section')->maxItems(count(self::SECTIONS)),
                    ]),

                    Tab::make('Categories & products')->icon('heroicon-o-rectangle-group')->schema([
                        Section::make('Shop by category')->columns(3)->schema([
                            TextInput::make("$g.categories_heading")->label('Heading'),
                            TextInput::make("$g.categories_text")->label('Sub-heading'),
                            TextInput::make("$g.categories_limit")->label('Categories shown')->numeric()->minValue(4)->maxValue(12),
                        ]),
                        $rail('featured', 'Most ordered'),
                        $rail('deals', 'Today’s deals', 12),
                        $rail('bestsellers', 'Bestsellers'),
                        $rail('new', 'New arrivals'),
                        Section::make('Brands')->description('Brands are read from the products’ brand field — no separate list to maintain.')->columns(2)->schema([
                            TextInput::make("$g.brands_heading")->label('Heading'),
                            TextInput::make("$g.brands_text")->label('Sub-heading'),
                        ]),
                    ]),

                    Tab::make('Trust & FAQ')->icon('heroicon-o-shield-check')->schema([
                        Section::make('Why choose us')->schema([
                            TextInput::make("$g.why_heading")->label('Heading'),
                            Repeater::make("$g.why_items")->label('Points')->schema([
                                Select::make('icon')->options(['bolt' => 'Bolt', 'box' => 'Box', 'truck' => 'Truck', 'receipt' => 'Invoice', 'tag' => 'Tag', 'shield' => 'Shield', 'headset' => 'Headset', 'rotate' => 'Returns', 'badge' => 'Badge', 'check' => 'Check'])->native(false),
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
