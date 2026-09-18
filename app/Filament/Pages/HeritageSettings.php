<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Forms\Components\TagsInput;
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

/** Header, promo bar, search, support and footer settings for the Heritage Grocery template. */
class HeritageSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Heritage — Settings';

    protected static ?string $navigationLabel = 'Heritage · Settings';

    protected function settingGroups(): array
    {
        return ['heritage_site'];
    }

    protected function defaultsFor(string $group): array
    {
        return app(TemplateManager::class)->get('heritage')->defaults()['site'] ?? [];
    }

    public function form(Schema $schema): Schema
    {
        $g = 'heritage_site';

        return $schema->statePath('data')->components([
            Tabs::make('Heritage settings')->persistTabInQueryString()->tabs([
                Tab::make('Brand & header')->icon('heroicon-o-building-storefront')->schema([
                    Section::make('Wordmark')->columns(3)->schema([
                        TextInput::make("$g.logo_primary")->label('Wordmark — first word')->helperText('Serif, deep red.'),
                        TextInput::make("$g.logo_accent")->label('Wordmark — accent word')->helperText('Italic, gold.'),
                        TextInput::make("$g.tagline")->label('Tagline'),
                    ]),
                    Section::make('Search & delivery')->columns(2)->schema([
                        TextInput::make("$g.search_placeholder")->label('Search placeholder'),
                        TextInput::make("$g.delivery_note")->label('Delivery note')->helperText('Shown beside the search on desktop.'),
                        TagsInput::make("$g.search_suggestions")->label('Popular searches')->reorderable()->columnSpan(2),
                    ]),
                    Section::make('Navigation labels')->columns(3)->schema([
                        TextInput::make("$g.nav_featured_label")->label('First link label')->helperText('Highlighted link, e.g. Festive Special.'),
                        TextInput::make("$g.nav_featured_url")->label('First link URL'),
                        TextInput::make("$g.nav_all_label")->label('All products label'),
                        TextInput::make("$g.nav_category_label")->label('Category dropdown label'),
                        TextInput::make("$g.nav_need_label")->label('Need dropdown label'),
                        TextInput::make("$g.logo_sub")->label('Logo subline')->helperText('Small line under the wordmark in the round logo.'),
                    ]),
                    Section::make('Filters')->schema([
                        Toggle::make("$g.show_diet_filter")->label('Show the dietary preference filter on listings'),
                    ]),
                ]),
                Tab::make('Promo bar')->icon('heroicon-o-megaphone')->schema([
                    TagsInput::make("$g.promo_messages")->label('Messages')->helperText('Rotate automatically. Leave empty to hide the bar.')->reorderable(),
                    Grid::make(2)->schema([
                        TextInput::make("$g.announcement_cta_label")->label('Link label'),
                        TextInput::make("$g.announcement_cta_url")->label('Link URL'),
                    ]),
                ]),
                Tab::make('Support')->icon('heroicon-o-lifebuoy')->schema([
                    Grid::make(3)->schema([
                        TextInput::make("$g.support_phone")->label('Support phone')->helperText('Falls back to Site settings → contact phone.'),
                        TextInput::make("$g.support_hours")->label('Support hours'),
                        TextInput::make("$g.support_toll_free")->label('Toll-free number'),
                        TextInput::make("$g.support_email")->label('Support email')->helperText('Falls back to Site settings → contact email.'),
                        TextInput::make("$g.gifting_email")->label('Corporate gifting email'),
                        TextInput::make("$g.whatsapp_number")->label('WhatsApp number')->helperText('International format, e.g. 919876543210.'),
                    ]),
                ]),
                Tab::make('Footer')->icon('heroicon-o-rectangle-stack')->schema([
                    Textarea::make("$g.footer_blurb")->label('Footer blurb')->rows(3),
                    TagsInput::make("$g.certifications")->label('Certifications / trust marks')->helperText('Shown as gold badges in the footer.')->reorderable(),
                ]),
            ]),
        ]);
    }
}
