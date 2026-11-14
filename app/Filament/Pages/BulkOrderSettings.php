<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
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
 * The Bulk Order store's look and wording: brand colour, announcement, home banner,
 * Bulk order section and the support details. Store name, logo text, checkout, tax and SEO
 * stay in Site settings.
 */
class BulkOrderSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Bulk Order — Settings';

    protected static ?string $navigationLabel = 'Bulk Order · Settings';

    protected function settingGroups(): array
    {
        return ['bulk_site'];
    }

    protected function defaultsFor(string $group): array
    {
        return app(TemplateManager::class)->get('bulk')->defaults()['site'] ?? [];
    }

    public function form(Schema $schema): Schema
    {
        $g = 'bulk_site';

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Bulk Order settings')->persistTabInQueryString()->tabs([
                    Tab::make('Brand')->icon('heroicon-o-swatch')->schema([
                        ColorPicker::make("$g.brand_color")->label('Brand colour')
                            ->helperText('Header bar, buttons and highlights. Pick a dark shade so white text stays readable.'),
                        Fields::image("$g.login_image", 'Customer sign-in image (portrait, shown behind the brand panel on sign in and create account)', 'bulk'),
                        TextInput::make("$g.announcement")->label('Announcement strip')->helperText('Shown above the header, e.g. a delivery or invoice promise you actually offer. Leave blank to hide it.'),
                    ]),
                    Tab::make('Home page')->icon('heroicon-o-home')->schema([
                        Section::make('Banner')->schema([
                            TextInput::make("$g.hero_eyebrow")->label('Small line above the heading'),
                            Textarea::make("$g.hero_heading")->label('Heading')->rows(2),
                            Textarea::make("$g.hero_text")->label('Text')->rows(2),
                            Grid::make(2)->schema([
                                TextInput::make("$g.hero_cta_label")->label('Main button')->helperText('Goes to the Bulk order section.'),
                                TextInput::make("$g.hero_secondary_label")->label('Second button')->helperText('Goes to the catalogue.'),
                            ]),
                        ]),
                        Toggle::make("$g.show_stock")->label('Show stock levels on products'),
                    ]),
                    Tab::make('Bulk order')->icon('heroicon-o-clipboard-document-list')->schema([
                        TextInput::make("$g.heading")->label('Heading'),
                        Textarea::make("$g.intro")->label('Introduction')->rows(2),
                        Grid::make(2)->schema([
                            TextInput::make("$g.search_placeholder")->label('Item box placeholder'),
                            TextInput::make("$g.empty_text")->label('Shown when no item matches'),
                        ]),
                        Section::make('Paste a list tab')->schema([
                            TextInput::make("$g.pad_heading")->label('Heading'),
                            Textarea::make("$g.pad_help")->label('Instructions')->rows(2),
                            Textarea::make("$g.pad_example")->label('Example shown in the empty box')->rows(3)
                                ->helperText('One line per product: a code, then the quantity. Use real codes from your catalogue.'),
                        ]),
                    ]),
                    Tab::make('Support & footer')->icon('heroicon-o-lifebuoy')->schema([
                        Grid::make(3)->schema([
                            TextInput::make("$g.support_email")->label('Orders email (override)')->email()->placeholder('Uses Site settings → Client care email')->helperText('Only fill in to show a different address on this template.'),
                            TextInput::make("$g.support_phone")->label('Orders phone (override)')->placeholder('Uses Site settings → Contact phone')->helperText('Only fill in to show a different number on this template.'),
                            TextInput::make("$g.support_hours")->label('Hours'),
                        ]),
                        Textarea::make("$g.footer_blurb")->label('Footer blurb')->rows(2),
                    ]),
                ]),
            ]);
    }
}
