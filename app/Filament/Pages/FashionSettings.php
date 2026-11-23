<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Header, announcement bar and footer settings for the Fashion template. Fashion keeps them in
 * the shared "site" group (its settings alias), so this page edits only these keys of it; the
 * rest of the group belongs to Site settings, and saving merges rather than replaces.
 */
class FashionSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Fashion — Settings';

    protected static ?string $navigationLabel = 'Fashion · Settings';

    protected function settingGroups(): array
    {
        return ['site'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make('Fashion settings')->persistTabInQueryString()->tabs([
                Tab::make('Brand & header')->icon('heroicon-o-building-storefront')->schema([
                    Section::make('Wordmark')->columns(2)->schema([
                        TextInput::make('site.logo_primary')->label('Wordmark — first word')->placeholder('MAISON')->helperText('The main word of your store name, shown in capitals in a serif font. Examples: MAISON, AURA, VELVET.'),
                        TextInput::make('site.logo_accent')->label('Wordmark — accent word')->placeholder('Élan')->helperText('A second word shown in italic right after the first. Examples: Élan, Studio, Atelier — together they read "MAISON Élan".'),
                    ]),
                    Fields::image('site.page_header_image', 'Page header background (landscape, used on shop, policies, account and info pages)', 'site'),
                ]),
                Tab::make('Announcement bar')->icon('heroicon-o-megaphone')->schema([
                    TextInput::make('site.announcement_text')->label('Message')->helperText('Leave empty to hide the bar.'),
                    TextInput::make('site.announcement_text_short')->label('Short message (mobile)'),
                    Grid::make(2)->schema([
                        TextInput::make('site.announcement_link_label')->label('Link label'),
                        TextInput::make('site.announcement_link_url')->label('Link URL'),
                    ]),
                ]),
                Tab::make('Support')->icon('heroicon-o-lifebuoy')->schema([
                    TextInput::make('site.contact_hours')->label('Support hours')->helperText('Shown in the footer and mobile menu. Phone and email are in Site settings → Contact & social.'),
                ]),
                Tab::make('Footer')->icon('heroicon-o-rectangle-stack')->schema([
                    Textarea::make('site.footer_blurb')->label('Footer blurb')->rows(3),
                    TextInput::make('site.footer_newsletter_heading')->label('Footer newsletter heading'),
                ]),
            ]),
        ]);
    }
}
