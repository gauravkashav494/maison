<?php

namespace App\Filament\Pages;

use App\Admin\StoreContext;
use App\Clients\ClientAccess;
use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * SEO → SEO settings: the site-wide defaults every page falls back to (homepage title, title
 * suffix, description, share image), search engine visibility, verification and analytics.
 * Stored in the "seo" setting group, per client. Needs the "SEO" module's Edit permission.
 */
class SeoSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'SEO settings';

    protected static ?string $slug = 'seo-settings';

    public static function canAccess(): bool
    {
        return app(StoreContext::class)->templateObject() !== null && ClientAccess::current('seo', 'update');
    }

    protected function settingGroups(): array
    {
        return ['seo'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Search engine visibility')->icon('heroicon-o-eye')->schema([
                    Toggle::make('seo.discourage_indexing')
                        ->label('Discourage search engines from indexing this site')
                        ->helperText('Adds noindex, nofollow to every page, an X-Robots-Tag header, and blocks all crawlers in robots.txt. Turn this OFF when the site goes live.'),
                ]),
                Section::make('Titles & descriptions')->icon('heroicon-o-document-text')
                    ->description('Used by every page that has no title, description or share image of its own (SEO → Search listings).')
                    ->schema([
                        TextInput::make('seo.default_title')->label('Homepage title')->maxLength(70),
                        TextInput::make('seo.title_suffix')->label('Title suffix')->helperText('Appended to every page title, e.g. " — EcomVision".'),
                        Textarea::make('seo.default_description')->label('Default meta description')->rows(3)->maxLength(320),
                        Fields::image('seo.default_og_image', 'Default social share image (1200×630)', 'seo'),
                    ]),
                Section::make('Verification & analytics')->icon('heroicon-o-chart-bar')->schema([
                    TextInput::make('seo.google_site_verification')->label('Google Search Console verification code'),
                    TextInput::make('seo.gtag_id')->label('Google Analytics measurement ID')->placeholder('G-XXXXXXXXXX'),
                    Textarea::make('seo.robots_extra')->label('Extra robots.txt rules')->rows(3)->placeholder("Disallow: /private\n"),
                ]),
            ]);
    }
}
