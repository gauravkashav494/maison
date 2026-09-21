<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Header, search, support, bulk-quote and footer settings for the Plumbing template.
 * Brand contact details, social links, SEO and checkout stay in Site settings.
 */
class PlumbingSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Plumbing — Settings';

    protected static ?string $navigationLabel = 'Plumbing · Settings';

    protected function settingGroups(): array
    {
        return ['plumbing_site'];
    }

    protected function defaultsFor(string $group): array
    {
        return app(TemplateManager::class)->get('plumbing')->defaults()['site'] ?? [];
    }

    public function form(Schema $schema): Schema
    {
        $g = 'plumbing_site';

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Plumbing settings')->persistTabInQueryString()->tabs([
                    Tab::make('Header')->icon('heroicon-o-window')->schema([
                        Section::make('Logo & top strip')->columns(2)->schema([
                            TextInput::make("$g.logo_primary")->label('Logo — first word')->helperText('Rendered in deep blue.'),
                            TextInput::make("$g.logo_accent")->label('Logo — accent word')->helperText('Rendered in bright blue.'),
                            TextInput::make("$g.tagline")->label('Tagline')->columnSpan(2),
                            TagsInput::make("$g.usp_strip")->label('Top strip promises')->helperText('Short trust lines shown in the strip above the header, e.g. "Pan-India delivery".')->columnSpan(2),
                        ]),
                        Section::make('Search')->columns(1)->schema([
                            TextInput::make("$g.search_placeholder")->label('Search placeholder'),
                            TagsInput::make("$g.search_suggestions")->label('Popular searches')->helperText('Rotate in the placeholder and appear as chips when the search box is empty.'),
                        ]),
                    ]),
                    Tab::make('Support & delivery')->icon('heroicon-o-phone')->schema([
                        Section::make('Support')->columns(2)->schema([
                            TextInput::make("$g.support_phone")->label('Support phone'),
                            TextInput::make("$g.support_hours")->label('Support hours'),
                            TextInput::make("$g.whatsapp_number")->label('WhatsApp number')->helperText('International format without +, e.g. 919876543210. Leave blank to hide WhatsApp buttons.'),
                            TextInput::make("$g.support_email")->label('Support email')->email(),
                        ]),
                        Section::make('Delivery & GST messaging')->columns(1)->schema([
                            TextInput::make("$g.delivery_promise")->label('Delivery promise')->helperText('Shown on product pages and in the cart.'),
                            TextInput::make("$g.gst_note")->label('GST note')->helperText('Shown under prices on product pages.'),
                        ]),
                    ]),
                    Tab::make('Bulk quote')->icon('heroicon-o-building-office-2')->schema([
                        Section::make('Bulk / professional buying band')->description('Shown on the homepage and product pages. The button links to the contact form by default.')->columns(2)->schema([
                            TextInput::make("$g.bulk_heading")->label('Heading'),
                            TextInput::make("$g.bulk_cta_label")->label('Button label'),
                            Textarea::make("$g.bulk_text")->label('Text')->rows(2)->columnSpan(2),
                            TextInput::make("$g.bulk_cta_url")->label('Button link')->placeholder('/contact?subject=Bulk+quote'),
                            TagsInput::make("$g.bulk_points")->label('Benefit points')->columnSpan(2),
                        ]),
                    ]),
                    Tab::make('Footer')->icon('heroicon-o-rectangle-stack')->schema([
                        Textarea::make("$g.footer_blurb")->label('Footer blurb')->rows(2),
                        Section::make('Newsletter')->columns(2)->schema([
                            TextInput::make("$g.newsletter_heading")->label('Heading'),
                            TextInput::make("$g.newsletter_text")->label('Text'),
                        ]),
                    ]),
                ]),
            ]);
    }
}
