<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Business details for the Plumbing Services template: name & logo, phone / WhatsApp /
 * email / address / hours, emergency availability, trust strip, stats, social links,
 * footer and quote copy. Every Call / WhatsApp button on the site reads from here.
 */
class PlumbingServicesSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 50;

    protected static ?string $title = 'Plumbing Services — Business settings';

    protected static ?string $navigationLabel = 'Plumbing Services · Business';

    protected function settingGroups(): array
    {
        return ['plumbing_services_site'];
    }

    protected function defaultsFor(string $group): array
    {
        return app(TemplateManager::class)->get('plumbing-services')->defaults()['site'] ?? [];
    }

    public function form(Schema $schema): Schema
    {
        $g = 'plumbing_services_site';

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Plumbing Services settings')->persistTabInQueryString()->tabs([
                    Tab::make('Business')->icon('heroicon-o-building-storefront')->schema([
                        Section::make('Name & logo')->columns(2)->schema([
                            TextInput::make("$g.business_name")->label('Business name')->required()->columnSpan(2)->helperText('Used in the PWA name, structured data and the footer.'),
                            TextInput::make("$g.logo_primary")->label('Logo — first word')->helperText('Rendered in deep blue.'),
                            TextInput::make("$g.logo_accent")->label('Logo — accent word')->helperText('Rendered in bright blue.'),
                            TextInput::make("$g.tagline")->label('Tagline')->columnSpan(2),
                        ]),
                        Section::make('Contact')->columns(2)->schema([
                            TextInput::make("$g.phone")->label('Phone')->required()->helperText('Shown on every Call button.'),
                            TextInput::make("$g.emergency_phone")->label('Emergency phone')->helperText('Leave blank to use the main phone.'),
                            TextInput::make("$g.whatsapp_number")->label('WhatsApp number')->helperText('International format without +, e.g. 919876543210. Leave blank to hide WhatsApp buttons.'),
                            TextInput::make("$g.whatsapp_message")->label('WhatsApp pre-filled message'),
                            TextInput::make("$g.email")->label('Email')->email(),
                            TextInput::make("$g.hours")->label('Working hours'),
                            Textarea::make("$g.address")->label('Address')->rows(2)->columnSpan(2),
                        ]),
                        Section::make('Service promise')->columns(2)->schema([
                            TextInput::make("$g.default_area")->label('Default service area label')->helperText('Shown in the mobile header before the visitor picks a city.'),
                            TextInput::make("$g.response_note")->label('Response note')->helperText('e.g. "Plumber at your door in 60–90 minutes in most areas".'),
                            TagsInput::make("$g.usp_strip")->label('Trust strip')->helperText('Short promises shown in the top strip and hero, e.g. "Verified plumbers".')->columnSpan(2),
                            Repeater::make("$g.stats")->label('Stats')->schema([TextInput::make('value')->required(), TextInput::make('label')->required()])->columns(2)->maxItems(4)->columnSpan(2),
                        ]),
                    ]),
                    Tab::make('Emergency')->icon('heroicon-o-exclamation-triangle')->schema([
                        Section::make('Emergency availability')->columns(1)->schema([
                            Toggle::make("$g.emergency_available")->label('24×7 emergency service available')->helperText('Turns the emergency messaging, quick action and red accents on or off.'),
                            TextInput::make("$g.emergency_note")->label('Emergency note')->helperText('Shown in the header strip and on the emergency page.'),
                        ]),
                    ]),
                    Tab::make('Search')->icon('heroicon-o-magnifying-glass')->schema([
                        Section::make('Service search')->columns(1)->schema([
                            TextInput::make("$g.search_placeholder")->label('Search placeholder'),
                            TagsInput::make("$g.search_suggestions")->label('Rotating examples')->helperText('Rotate in the mobile search placeholder and appear as chips when the search is empty.'),
                        ]),
                    ]),
                    Tab::make('Quote')->icon('heroicon-o-document-text')->schema([
                        Section::make('Quote request block')->description('Shown on the homepage and the quote page. No prices are shown anywhere unless a service has a price note.')->columns(1)->schema([
                            TextInput::make("$g.quote_heading")->label('Heading'),
                            Textarea::make("$g.quote_text")->label('Text')->rows(3),
                        ]),
                    ]),
                    Tab::make('Footer & social')->icon('heroicon-o-rectangle-stack')->schema([
                        Textarea::make("$g.footer_blurb")->label('Footer blurb')->rows(3),
                        TextInput::make("$g.footer_note")->label('Footer trust line'),
                        Repeater::make("$g.social")->label('Social links')->schema([
                            Select::make('network')->options(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'x' => 'X'])->required()->native(false),
                            TextInput::make('url')->label('URL')->required(),
                        ])->columns(2)->maxItems(5)->helperText('Links set to "#" are hidden.'),
                    ]),
                ]),
            ]);
    }
}
