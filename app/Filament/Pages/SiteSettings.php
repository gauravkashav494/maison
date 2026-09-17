<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
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

class SiteSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Site settings';

    protected function settingGroups(): array
    {
        return ['site', 'seo', 'checkout'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Settings')->persistTabInQueryString()->tabs([
                    Tab::make('Brand')->icon('heroicon-o-building-storefront')->schema([
                        Grid::make(3)->schema([
                            TextInput::make('site.name')->label('Site name')->required(),
                            TextInput::make('site.logo_primary')->label('Wordmark — first word')->helperText('Rendered in uppercase serif.'),
                            TextInput::make('site.logo_accent')->label('Wordmark — accent word')->helperText('Rendered in italic.'),
                        ]),
                        TextInput::make('site.tagline'),
                        Textarea::make('site.description')->rows(2)->helperText('Used as the default site description.'),
                        Textarea::make('site.footer_blurb')->label('Footer blurb')->rows(2),
                        TextInput::make('site.footer_newsletter_heading')->label('Footer newsletter heading'),
                        Fields::image('site.page_header_image', 'Page header background (landscape, used on shop, policies, account and info pages)', 'site'),
                    ]),
                    Tab::make('Announcement bar')->icon('heroicon-o-megaphone')->schema([
                        TextInput::make('site.announcement_text')->label('Message'),
                        TextInput::make('site.announcement_text_short')->label('Short message (mobile)'),
                        Grid::make(2)->schema([
                            TextInput::make('site.announcement_link_label')->label('Link label'),
                            TextInput::make('site.announcement_link_url')->label('Link URL'),
                        ]),
                        TextInput::make('site.free_shipping_threshold')->numeric()->prefix('₹')->label('Free shipping threshold')->helperText('Drives the progress bar in the shopping bag.'),
                    ]),
                    Tab::make('Contact & social')->icon('heroicon-o-at-symbol')->schema([
                        Grid::make(3)->schema([
                            TextInput::make('site.contact_email')->email()->label('Client care email'),
                            TextInput::make('site.contact_phone')->label('Phone'),
                            TextInput::make('site.contact_hours')->label('Hours'),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('site.social_instagram')->url()->label('Instagram'),
                            TextInput::make('site.social_pinterest')->url()->label('Pinterest'),
                            TextInput::make('site.social_facebook')->url()->label('Facebook'),
                            TextInput::make('site.social_youtube')->url()->label('YouTube'),
                        ]),
                        TagsInput::make('site.payment_methods')->label('Payment method badges')->reorderable(),
                    ]),
                    Tab::make('Checkout')->icon('heroicon-o-credit-card')->schema([
                        Section::make('Shipping methods')->description('Offered at checkout in this order. Leave “Free over” blank to always charge.')->schema([
                            Repeater::make('checkout.shipping_methods')->hiddenLabel()->schema([
                                TextInput::make('name')->required(),
                                TextInput::make('code')->required()->alphaDash()->helperText('Internal id, e.g. standard'),
                                TextInput::make('cost')->numeric()->prefix('₹')->default(0)->required(),
                                TextInput::make('free_over')->label('Free over')->numeric()->prefix('₹'),
                                TextInput::make('eta')->label('Delivery estimate')->placeholder('3–5 business days'),
                                TextInput::make('description')->columnSpanFull(),
                            ])->columns(5)->reorderable()->itemLabel(fn (array $state) => $state['name'] ?? null),
                        ]),
                        Section::make('Payments & tax')->columns(2)->schema([
                            CheckboxList::make('checkout.payment_methods')->label('Enabled payment methods')->options(\App\Models\Order::PAYMENT_METHODS)->columns(2)->columnSpanFull(),
                            TextInput::make('checkout.cod_fee')->label('Cash on delivery fee')->numeric()->prefix('₹')->default(0),
                            TextInput::make('checkout.tax_rate')->label('Tax rate added at checkout')->numeric()->suffix('%')->default(0)->helperText('Leave at 0 if prices already include tax.'),
                            TextInput::make('checkout.tax_label')->label('Tax label')->default('GST'),
                            TagsInput::make('checkout.gift_card_amounts')->label('Gift card values')->helperText('Whole rupees, e.g. 2500'),
                        ]),
                    ]),
                    Tab::make('SEO defaults')->icon('heroicon-o-magnifying-glass')->schema([
                        Section::make('Search engine visibility')->schema([
                            Toggle::make('seo.discourage_indexing')
                                ->label('Discourage search engines from indexing this site')
                                ->helperText('Adds noindex, nofollow to every page, an X-Robots-Tag header, and blocks all crawlers in robots.txt. Turn this OFF when the site goes live.'),
                        ]),
                        Section::make('Titles & descriptions')->schema([
                            TextInput::make('seo.default_title')->label('Homepage title')->maxLength(70),
                            TextInput::make('seo.title_suffix')->label('Title suffix')->helperText('Appended to every page title, e.g. " — Maison Élan".'),
                            Textarea::make('seo.default_description')->label('Default meta description')->rows(3)->maxLength(320),
                            Fields::image('seo.default_og_image', 'Default social share image (1200×630)', 'seo'),
                        ]),
                        Section::make('Verification & analytics')->schema([
                            TextInput::make('seo.google_site_verification')->label('Google Search Console verification code'),
                            TextInput::make('seo.gtag_id')->label('Google Analytics measurement ID')->placeholder('G-XXXXXXXXXX'),
                            Textarea::make('seo.robots_extra')->label('Extra robots.txt rules')->rows(3)->placeholder("Disallow: /private\n"),
                        ]),
                    ]),
                ]),
            ]);
    }
}
