<?php

namespace App\Filament\Pages;

use App\Clients\ClientContext;
use App\Filament\Auth\Login;
use App\Filament\Pages\Concerns\HasSettingsForm;
use App\Filament\Support\Fields;
use App\Models\Order;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class SiteSettings extends Page
{
    use HasSettingsForm;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Site settings';

    /** Tax names a store can show customers: the value is what is printed, so keys and labels match. */
    public const TAX_NAMES = [
        'GST' => 'GST',
        'IGST' => 'IGST',
        'CGST + SGST' => 'CGST + SGST',
        'VAT' => 'VAT',
        'Tax' => 'Tax',
    ];

    /** GST slabs (stored as the number; 0 means no tax). */
    public const GST_RATES = [
        '0' => '0% — no tax',
        '0.25' => '0.25%',
        '3' => '3%',
        '5' => '5%',
        '12' => '12%',
        '18' => '18%',
        '28' => '28%',
        '40' => '40%',
    ];

    /** VAT rates. */
    public const VAT_RATES = [
        '0' => '0% — no tax',
        '5' => '5%',
        '12.5' => '12.5%',
        '20' => '20%',
    ];

    /**
     * Rates offered for a tax name: GST names get the GST slabs, VAT its own rates, and the
     * generic "Tax" both, so a GST store cannot pick 20% by mistake.
     */
    public static function taxRatesFor(?string $name): array
    {
        $rates = match ($name) {
            'VAT' => self::VAT_RATES,
            'Tax' => self::GST_RATES + self::VAT_RATES,
            default => self::GST_RATES,
        };
        uksort($rates, fn ($a, $b) => (float) $a <=> (float) $b);

        return $rates;
    }

    protected function settingGroups(): array
    {
        return ['site', 'seo', 'checkout', 'login', 'reviews'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Settings')->persistTabInQueryString()->tabs([
                    Tab::make('Brand')->icon('heroicon-o-building-storefront')->schema([
                        TextInput::make('site.name')->label('Site name')->required(),
                        Textarea::make('site.description')->rows(2)->helperText('Used as the default site description.'),
                        Callout::make('Logo, header and footer')
                            ->description('Set under Appearance → your template\'s Settings page: wordmark, announcement or promo bar, footer and support details.')
                            ->info(),
                    ]),
                    Tab::make('Contact & social')->icon('heroicon-o-at-symbol')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('site.contact_email')->email()->label('Client care email'),
                            TextInput::make('site.contact_phone')->label('Phone'),
                        ]),
                        Callout::make('Used everywhere on the store')
                            ->description('Shown on the storefront, in emails and in search-engine data. A template can show a different support phone or email: Appearance → its Settings page → Support.')
                            ->info(),
                        Grid::make(2)->schema([
                            TextInput::make('site.social_instagram')->url()->label('Instagram'),
                            TextInput::make('site.social_pinterest')->url()->label('Pinterest'),
                            TextInput::make('site.social_facebook')->url()->label('Facebook'),
                            TextInput::make('site.social_youtube')->url()->label('YouTube'),
                        ]),
                        TagsInput::make('site.payment_methods')->label('Payment method badges')->reorderable(),
                    ]),
                    Tab::make('Checkout')->icon('heroicon-o-credit-card')->schema([
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
                            ->description('How tax is worked out and shown on the cart, checkout and invoices.')
                            ->columns(2)
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
                                    ->default('exclusive')->live()->columnSpanFull(),
                                // A fixed list, not free text: the name is printed on every cart, order and invoice.
                                Select::make('checkout.tax_label')->label('Tax name')
                                    ->options(self::TAX_NAMES)
                                    ->default('GST')->required()->selectablePlaceholder(false)->native(false)
                                    // The rates offered depend on the name; a rate the new name does not have goes back to 0%.
                                    ->live()
                                    ->afterStateUpdated(function ($get, $set, ?string $state) {
                                        if (! array_key_exists((string) $get('checkout.tax_rate'), self::taxRatesFor($state))) {
                                            $set('checkout.tax_rate', '0');
                                        }
                                    })
                                    ->validationMessages([
                                        'required' => 'Choose the tax name customers see.',
                                        'in' => 'Choose one of the listed tax names.',
                                    ])
                                    ->helperText('Shown to customers on the cart, checkout and invoices.'),
                                // Chosen from the rates for the tax name above, not typed: a mistyped rate would be charged on every order.
                                Select::make('checkout.tax_rate')->label('Tax rate')
                                    ->options(fn ($get) => self::taxRatesFor($get('checkout.tax_label')))
                                    // Rates saved earlier are numbers (18, 18.0); the options are keyed "18", and the select compares strictly.
                                    ->formatStateUsing(fn ($state) => is_numeric($state) ? (string) (float) $state : $state)
                                    // A plain <select>: its options are rendered on the server and redrawn when the
                                    // tax name changes. The searchable dropdown kept showing only the selected rate.
                                    ->default('0')->required()->selectablePlaceholder(false)->native()
                                    ->validationMessages([
                                        'required' => 'Choose the tax rate, or 0% if you do not charge tax.',
                                        'in' => 'Choose one of the listed tax rates.',
                                    ])
                                    ->helperText(fn ($get) => $get('checkout.tax_mode') === 'inclusive'
                                        ? 'Used to show how much of each order is tax. At 0, the tax line is hidden.'
                                        : 'Added on top of every order at checkout. At 0, no tax is charged and the tax line is hidden.'),
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
                        ->visible(fn () => ! app(ClientContext::class)->client()?->templateObject()?->supportsServices())
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
                    Tab::make('SEO defaults')->icon('heroicon-o-magnifying-glass')->schema([
                        Section::make('Search engine visibility')->schema([
                            Toggle::make('seo.discourage_indexing')
                                ->label('Discourage search engines from indexing this site')
                                ->helperText('Adds noindex, nofollow to every page, an X-Robots-Tag header, and blocks all crawlers in robots.txt. Turn this OFF when the site goes live.'),
                        ]),
                        Section::make('Titles & descriptions')->schema([
                            TextInput::make('seo.default_title')->label('Homepage title')->maxLength(70),
                            TextInput::make('seo.title_suffix')->label('Title suffix')->helperText('Appended to every page title, e.g. " — EcomVision".'),
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
