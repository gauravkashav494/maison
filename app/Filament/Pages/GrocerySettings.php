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

/**
 * Header, offer strip, search, support and footer settings for the Indian Grocery
 * template. Brand contact details, social links, SEO and checkout stay in Site settings.
 */
class GrocerySettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Grocery — Settings';

    protected static ?string $navigationLabel = 'Grocery · Settings';

    protected function settingGroups(): array
    {
        return ['grocery_site'];
    }

    protected function defaultsFor(string $group): array
    {
        return app(TemplateManager::class)->get('grocery')->defaults()['site'] ?? [];
    }

    public function form(Schema $schema): Schema
    {
        $g = 'grocery_site';

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Grocery settings')->persistTabInQueryString()->tabs([
                    Tab::make('Header')->icon('heroicon-o-window')->schema([
                        Section::make('Logo & delivery promise')->columns(2)->schema([
                            TextInput::make("$g.logo_primary")->label('Logo — first word')->helperText('Rendered in bold green.'),
                            TextInput::make("$g.logo_accent")->label('Logo — accent word')->helperText('Rendered in saffron.'),
                            TextInput::make("$g.tagline")->label('Tagline'),
                            TextInput::make("$g.delivery_promise")->label('Delivery promise')->helperText('Shown next to the location, e.g. "Delivery in 10–30 min".'),
                            TextInput::make("$g.delivery_area")->label('Default delivery area')->helperText('City or locality shown in the header location picker.'),
                        ]),
                        Section::make('Search')->columns(1)->schema([
                            TextInput::make("$g.search_placeholder")->label('Search placeholder'),
                            TagsInput::make("$g.search_suggestions")->label('Popular searches')->helperText('Shown as chips when the search box is empty.')->reorderable(),
                        ]),
                        Section::make('Filters')->columns(2)->schema([
                            Toggle::make("$g.show_veg_filter")->label('Show the veg-only toggle on listings'),
                            TextInput::make("$g.veg_filter_label")->label('Veg toggle label'),
                        ]),
                    ]),
                    Tab::make('Offer strip')->icon('heroicon-o-megaphone')->schema([
                        TextInput::make("$g.offer_strip_text")->label('Message')->helperText('Leave blank to hide the strip above the header.'),
                        Grid::make(2)->schema([
                            TextInput::make("$g.offer_strip_link_label")->label('Link label'),
                            TextInput::make("$g.offer_strip_link_url")->label('Link URL'),
                        ]),
                    ]),
                    Tab::make('Support')->icon('heroicon-o-lifebuoy')->schema([
                        Grid::make(3)->schema([
                            TextInput::make("$g.support_phone")->label('Support phone')->helperText('Falls back to the contact phone in Site settings.'),
                            TextInput::make("$g.support_hours")->label('Support hours'),
                            TextInput::make("$g.whatsapp_number")->label('WhatsApp number')->helperText('International format, e.g. 919876543210. Leave blank to hide the WhatsApp button.'),
                        ]),
                    ]),
                    Tab::make('Footer & app')->icon('heroicon-o-device-phone-mobile')->schema([
                        Textarea::make("$g.footer_blurb")->label('Footer blurb')->rows(2),
                        Section::make('Mobile app promotion')->columns(2)->schema([
                            TextInput::make("$g.app_heading")->label('Heading'),
                            TextInput::make("$g.app_text")->label('Text'),
                            TextInput::make("$g.app_store_url")->label('App Store URL'),
                            TextInput::make("$g.play_store_url")->label('Google Play URL'),
                        ]),
                    ]),
                ]),
            ]);
    }
}
