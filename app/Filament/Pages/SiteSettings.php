<?php

namespace App\Filament\Pages;

use App\Clients\ClientContext;
use App\Clients\StoreFeatures;
use App\Filament\Auth\Login;
use App\Filament\Forms\Components\PhoneInput;
use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Resources\Clients\Schemas\ClientBusinessDetails;
use App\Filament\Support\Fields;
use App\Models\Order;
use App\Support\IndianStates;
use App\Templates\Template;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SiteSettings extends Page
{
    use HasSettingsForm;

    public const TABS_END_HOOK = 'site-settings.tabs.end';

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Site settings';

    protected function settingGroups(): array
    {
        return ['site', 'checkout', 'login', 'reviews'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Settings')->persistTabInQueryString()
                    // "View on storefront" for the open tab, at the right of the tab bar.
                    ->endRenderHooks([self::TABS_END_HOOK])
                    ->tabs([
                        Tab::make('Brand')->icon('heroicon-o-building-storefront')->schema([
                            TextInput::make('site.name')->label('Site name')->required(),
                            Textarea::make('site.description')->rows(2)->helperText('Used as the default site description.'),
                            // One logo for the whole store: header, mobile menu, footer, sign-in page and
                            // invoices. Without one, each shows the template's text wordmark.
                            Fields::image('site.logo', 'Logo', 'branding')
                                ->maxSize(2048)
                                ->helperText('Optional. Shown in the store header, menu, footer, sign-in page and on invoices. Without one, the store shows its name as text. A wide PNG or SVG with a transparent background works best, about 400×120.'),
                            Callout::make('Header and footer')
                                ->description('The text wordmark (shown when there is no logo), announcement or promo bar, footer and support details are set under Appearance → your template\'s Settings page.')
                                ->info(),
                        ]),
                        Tab::make('Contact & social')->icon('heroicon-o-at-symbol')->schema([
                            Grid::make(2)->schema([
                                TextInput::make('site.contact_email')->email()->label('Client care email'),
                                PhoneInput::make('site.contact_phone')->label('Phone'),
                                TextInput::make('site.contact_person')->label('Owner / contact person')->maxLength(120)
                                    ->helperText('For your records; not shown on the store.'),
                            ]),
                            Callout::make('Used everywhere on the store')
                                ->description('Shown on the storefront, in emails and in search-engine data. A template can show a different support phone or email: Appearance → its Settings page → Support.')
                                ->info(),
                            Grid::make(2)->schema([
                                TextInput::make('site.social_instagram')->url()->label('Instagram URL'),
                                TextInput::make('site.social_pinterest')->url()->label('Pinterest URL'),
                                TextInput::make('site.social_facebook')->url()->label('Facebook URL'),
                                TextInput::make('site.social_youtube')->url()->label('YouTube URL'),
                                TextInput::make('site.social_linkedin')->url()->label('LinkedIn URL')
                                    ->helperText('Shown in the footer of templates that have a LinkedIn icon (Plumbing).'),
                            ]),
                            TagsInput::make('site.payment_methods')->label('Payment method badges')->reorderable(),
                        ]),
                        Tab::make('Checkout')->icon('heroicon-o-credit-card')
                        // A service business takes bookings, not orders: no cart, checkout or invoices.
                            ->visible(fn () => ! static::isServiceBusiness())
                            ->schema([
                                Section::make('Shipping methods')
                                    ->description('Shown to customers at checkout in this order. Drag to reorder.')
                                    ->schema([
                                        Repeater::make('checkout.shipping_methods')->hiddenLabel()
                                            ->schema([
                                                Grid::make(['default' => 1, 'md' => 3])->schema([
                                                    TextInput::make('name')->label('Name shown to customers')->required()->columnSpan(['md' => 2]),
                                                    TextInput::make('code')->label('Code')->required()->alphaDash()->placeholder('standard')
                                                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Internal id saved on orders. Letters, numbers and dashes only.'),
                                                    TextInput::make('cost')->label('Cost')->numeric()->prefix('₹')->default(0)->required()
                                                        ->helperText('0 = always free'),
                                                    TextInput::make('free_over')->label('Free on orders over')->numeric()->prefix('₹')
                                                        ->helperText('Leave blank to always charge'),
                                                    TextInput::make('eta')->label('Delivery estimate')->placeholder('3–5 business days'),
                                                    TextInput::make('description')->label('Short description')->placeholder('Tracked courier')->columnSpanFull(),
                                                ]),
                                            ])
                                            ->reorderable()->collapsible()->cloneable()
                                            ->addActionLabel('Add shipping method')
                                            ->itemLabel(function (array $state): ?string {
                                                $name = $state['name'] ?? null;
                                                if (! $name) {
                                                    return 'New shipping method';
                                                }
                                                $cost = (float) ($state['cost'] ?? 0);
                                                $price = $cost > 0 ? '₹'.number_format($cost, 2) : 'Free';
                                                if ($cost > 0 && filled($state['free_over'] ?? null)) {
                                                    $price .= ' · free over ₹'.number_format((float) $state['free_over'], 2);
                                                }

                                                return $name.' — '.$price;
                                            }),
                                    ]),
                                Section::make('Free shipping')
                                    ->schema([
                                        TextInput::make('site.free_shipping_threshold')->numeric()->prefix('₹')->label('Free shipping threshold')->helperText('Drives the progress bar in the shopping bag. Leave at 0 to hide it.'),
                                    ]),
                                Section::make('Payment methods')
                                    ->description('Which ways customers can pay.')
                                    ->columns(2)
                                    ->schema([
                                        CheckboxList::make('checkout.payment_methods')->hiddenLabel()
                                            ->options(Order::PAYMENT_METHODS)->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->live()->columnSpanFull(),
                                        TextInput::make('checkout.cod_fee')->label('Cash on delivery fee')->numeric()->prefix('₹')->default(0)
                                            ->helperText('Extra charge added when the customer picks cash on delivery. 0 = no fee.')
                                            ->visible(fn ($get) => in_array('cod', (array) $get('checkout.payment_methods'), true))
                                            ->dehydratedWhenHidden(),
                                    ]),
                                Section::make('Tax')
                                    // Each product's rate comes from its tax (Catalogue → Taxes); this only says
                                    // whether the entered prices already include it.
                                    ->description('Each product\'s tax rate comes from the tax chosen on it (Catalogue → Taxes). Choose whether product prices already include that tax.')
                                    ->schema([
                                        Radio::make('checkout.tax_mode')->label('Product prices are entered')
                                            ->options([
                                                'exclusive' => 'Before tax — tax is added on top at checkout',
                                                'inclusive' => 'Including tax — the price the customer pays',
                                            ])
                                            ->descriptions([
                                                'exclusive' => 'Usual for trade (B2B) buyers. A ₹1,000.00 item with 18% GST costs ₹1,180.00.',
                                                'inclusive' => 'Usual for retail shoppers. A ₹1,000.00 item costs ₹1,000.00, of which ₹152.54 is GST.',
                                            ])
                                            ->default('exclusive')->required(),
                                    ]),
                                // Read only: every number is made from ids (Order::generateNumber()), so there is nothing to set.
                                Section::make('Order numbers')
                                    ->description('The number each order gets when it is placed, shown to the customer and on the order page.')
                                    ->schema([
                                        Text::make(fn () => new HtmlString(
                                            '<ul class="list-disc space-y-1 ps-5">'
                                            .'<li>Orders by customers, and a sales person\'s orders for a customer: <strong>'.e(Order::exampleNumber(code: 'K7QM')).'</strong> (your store number, the date, then a random code).</li>'
                                            .'<li>A sales person\'s orders for themselves: <strong>'.e(Order::exampleNumber(salesSelf: true, code: 'K7QM')).'</strong> (the storefront number first).</li>'
                                            .'</ul>'
                                        )),
                                    ]),
                                Section::make('Invoice')
                                    ->description('Your details as the seller, printed at the top of every order invoice (PDF).')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('checkout.invoice_business_name')->label('Registered business name')
                                            ->maxLength(120)->helperText('Leave blank to use the site name.'),
                                        TextInput::make('checkout.invoice_gstin')->label('GSTIN')
                                            ->maxLength(15)->placeholder('22AAAAA0000A1Z5')
                                            ->regex(ClientBusinessDetails::GSTIN_PATTERN)
                                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtoupper(trim($state)) : null)
                                            ->validationMessages(['regex' => 'Enter a valid 15-character GSTIN, e.g. 22AAAAA0000A1Z5.'])
                                            ->helperText('Leave blank if you are not GST-registered.'),
                                        TextInput::make('checkout.invoice_pan')->label('PAN')
                                            ->maxLength(10)->placeholder('AAAAA0000A')
                                            ->regex(ClientBusinessDetails::PAN_PATTERN)
                                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtoupper(trim($state)) : null)
                                            ->validationMessages(['regex' => 'Enter a valid 10-character PAN, e.g. AAAAA0000A.'])
                                            ->helperText('The business\'s PAN, for your records.'),
                                        // With the shipping state, decides IGST or CGST + SGST on each order.
                                        Select::make('checkout.invoice_state')->label('State you sell from')
                                            ->options(IndianStates::options())->searchable()->native(false)
                                            ->placeholder(fn ($get) => ($code = IndianStates::codeFromGstin($get('checkout.invoice_gstin')))
                                                ? 'From GSTIN: '.IndianStates::label($code) : 'Choose a state')
                                            ->helperText('Orders shipped within this state get CGST + SGST; to another state or abroad, IGST. Blank uses your GSTIN\'s state.'),
                                        TextInput::make('checkout.invoice_prefix')->label('Invoice number prefix')
                                            ->default('INV')->placeholder('INV')->maxLength(5)->alphaNum()
                                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtoupper(trim($state)) : null)
                                            ->helperText(fn ($get) => 'Up to 5 letters or numbers. Invoices are numbered in one series per financial year: '
                                                .(strtoupper((string) $get('checkout.invoice_prefix')) ?: 'INV').'/26-27/0001, /0002 … Changing it affects new invoices only.'),
                                        Textarea::make('checkout.invoice_address')->label('Business address')->rows(3)
                                            ->maxLength(500)->placeholder("Shop 4, Main Market\nNew Delhi 110001")
                                            ->helperText('One line per row, as it should print.'),
                                        Textarea::make('checkout.invoice_note')->label('Note at the foot of the invoice')->rows(3)
                                            ->maxLength(500)->placeholder('Thank you for shopping with us. Goods once sold can be returned within 7 days.')
                                            ->helperText('Optional: terms, return policy or bank details.'),
                                    ]),
                                Section::make('Gift cards')
                                    ->description('Values customers can choose when buying a gift card.')
                                    ->schema([
                                        TagsInput::make('checkout.gift_card_amounts')->hiddenLabel()->placeholder('Type an amount and press Enter')
                                            ->helperText('Whole rupees, e.g. 500, 1000, 2500'),
                                    ]),
                            ]),
                        Tab::make('Reviews')->icon('heroicon-o-star')
                            // Service stores have no product reviews.
                            ->visible(fn () => ! static::isServiceBusiness() && StoreFeatures::enabled('reviews'))
                            ->schema([
                                Section::make('Publishing reviews')
                                    ->description('Reviews shoppers write on product pages.')
                                    ->schema([
                                        Toggle::make('reviews.auto_publish_verified')
                                            ->label('Publish verified buyers\' reviews automatically')
                                            ->default(true)
                                            ->helperText('A shopper who is signed in and has received the product gets a "Verified purchase" badge, and their review goes live at once. Turn off to approve every review yourself.'),
                                        Callout::make('What waits for approval')
                                            ->icon('heroicon-o-shield-check')
                                            ->color('info')
                                            ->description(new HtmlString(
                                                'Always, even from a verified buyer: a review containing a link, an email address or a phone number.<br>'
                                                .'While the setting is on: reviews from guests and from shoppers who have not received the product.<br>'
                                                .'The star rating never decides: a 1★ review is published exactly like a 5★ one.'
                                            )),
                                    ]),
                            ]),
                        Tab::make('Sign-in page')->icon('heroicon-o-lock-closed')->schema([
                            Section::make('Your team\'s sign-in page')
                                ->description(fn () => 'How '.$this->loginUrl().' looks. Leave a field empty to use your store\'s default, shown in grey.')
                                ->columns(2)
                                ->schema([
                                    Select::make('login.style')->label('Style')->options(Login::STYLES)->native(false)
                                        ->placeholder(fn () => 'Store default — '.(Login::STYLES[$this->loginDefault('style')] ?? 'Classic')),
                                    Fields::image('login.image', 'Image (leave empty for the store\'s default image)', 'login'),
                                    TextInput::make('login.kicker')->label('Small heading')->maxLength(40)->placeholder(fn () => $this->loginDefault('kicker')),
                                    TextInput::make('login.heading')->label('Heading')->maxLength(60)->placeholder(fn () => $this->loginDefault('heading')),
                                    TextInput::make('login.subtitle')->label('Line under the heading')->maxLength(120)->placeholder(fn () => $this->loginDefault('subtitle'))->columnSpanFull(),
                                    TextInput::make('login.quote')->label('Image headline')->maxLength(80)->placeholder(fn () => $this->loginDefault('quote')),
                                    TextInput::make('login.quote_sub')->label('Image text')->maxLength(200)->placeholder(fn () => $this->loginDefault('quote_sub')),
                                    TextInput::make('login.help')->label('Help line under the form')->maxLength(160)->placeholder(fn () => $this->loginDefault('help'))->columnSpanFull(),
                                ]),
                        ]),
                    ]),
            ]);
    }

    /**
     * "View on storefront" for each tab that shows on the store, keyed by the tab's key: the page
     * that holds it, opened with the area outlined (?settings_preview=, read by
     * components/app-head), and a line saying where it shows. Empty without a store to open.
     *
     * @return array<string, array{url: string, text: string, label: string}>
     */
    public static function storefrontLinks(): array
    {
        $store = app(ClientContext::class)->client()?->publicUrl();
        if (! $store) {
            return [];
        }

        $links = [
            'Brand' => ['brand', '/', 'Your logo in the store header. The site name is the browser-tab title.'],
            'Contact & social' => ['contact', '/', 'Contact details, social links and payment badges show in the footer of every store page.'],
            'Checkout' => ['checkout', static::isServiceBusiness() ? null : '/checkout', 'Shipping, payment methods, tax and the free-shipping bar show in the shopping bag and at checkout.'],
            'Sign-in page' => ['login', '/client/login', 'Your team\'s sign-in page, as it looks with these settings.', 'View sign-in page'],
        ];

        $out = [];
        foreach ($links as $label => $link) {
            [$preview, $path, $text] = $link;
            if ($path !== null) {
                // The key Filament gives a tab inside the "data" form: "contact-social::data::tab".
                $out[Str::slug(Str::transliterate($label, strict: true)).'::data::tab'] = [
                    'url' => rtrim($store, '/').$path.'?settings_preview='.$preview,
                    'text' => $text,
                    'label' => $link[3] ?? 'View on storefront',
                ];
            }
        }

        return $out;
    }

    /** A service business (Plumbing Services): bookings instead of products, cart and orders. */
    protected static function isServiceBusiness(): bool
    {
        return (bool) app(ClientContext::class)->client()?->templateObject()?->supportsServices();
    }

    /** The store's own default for a sign-in page field (its template's), with the client's name filled in. */
    protected function loginDefault(string $key): ?string
    {
        $client = app(ClientContext::class)->client();
        $defaults = $client?->templateObject()?->teamLogin() ?? Template::defaultTeamLogin();
        $value = $defaults[$key] ?? null;

        return is_string($value) ? str_replace(':name', (string) ($client?->name ?? ''), $value) : null;
    }

    protected function loginUrl(): string
    {
        $client = app(ClientContext::class)->client();

        return $client ? $client->loginUrl() : '/client/login';
    }
}
