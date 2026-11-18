<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Clients\ClientDatabase;
use App\Clients\ClientModules;
use App\Clients\ClientQuota;
use App\Clients\ClientTemplateSwitch;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Storefront;
use App\Rules\AvailableClientDatabase;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components(array_values(static::sections()));
    }

    /** The wizard steps shared by the create and edit pages (create adds a "Client admin" step). */
    public static function steps(): array
    {
        $sections = static::sections();

        return [
            Step::make('Client & store')->icon('heroicon-o-building-storefront')
                ->description('Who they are and which store they run')
                ->schema([$sections['client']]),
            Step::make('Plan & limits')->icon('heroicon-o-credit-card')
                ->description('Allowances for products, images and storage')
                ->schema([$sections['plan']]),
            Step::make('Modules')->icon('heroicon-o-squares-2x2')
                ->description('What their Client Admin can use')
                ->schema([$sections['modules']]),
            Step::make('Address & database')->icon('heroicon-o-globe-alt')
                ->description('Where the store lives and how it starts')
                ->schema([$sections['address'], $sections['database']]),
        ];
    }

    /**
     * The form's sections by key, arranged into wizard steps by steps() for both the create and
     * edit pages, so they always use the same fields and rules.
     *
     * @return array{client: Section, address: Section, modules: Section, plan: Section, database: Section}
     */
    public static function sections(): array
    {
        return [
            'client' => Section::make('Client')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120)->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, $get, $state) => blank($get('subdomain')) ? $set('subdomain', Str::slug((string) $state)) : null),
                TextInput::make('subdomain')->label('Subdomain')
                    ->suffix(fn () => '.'.Client::baseDomain())
                    ->placeholder('acme')
                    ->required()->maxLength(63)->unique(ignoreRecord: true)
                    // A valid host label: lowercase letters, digits and hyphens, not starting or ending with a hyphen.
                    ->regex('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/')
                    ->notIn(['exit']) // /store/exit is the "leave this store" link
                    ->rule('not_regex:/^'.preg_quote(config('templates.theme_prefix', 'theme-'), '/').'/')
                    // A store template's slug is that store's own address.
                    ->rule(fn (?Client $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                        if ($record === null && filled($value) && ($store = Client::storeOnAddress((string) $value))) {
                            $fail(Client::storeAddressMessage($store));
                        }
                    })
                    ->validationMessages([
                        'regex' => 'Use lowercase letters, numbers and hyphens only, without a hyphen at the start or end.',
                        'not_regex' => 'Subdomains starting with "'.config('templates.theme_prefix', 'theme-').'" are kept for theme previews.',
                        'unique' => 'Another client already uses this subdomain.',
                    ])
                    ->disabled(fn (?Client $record) => $record !== null)
                    ->dehydrated()
                    ->helperText(fn (?Client $record) => $record
                        ? 'Fixed: the store\'s address and its database are named after it.'
                        : 'The store\'s web address. It also names the database, so it cannot be changed later.'),
                Select::make('storefront_id')
                    ->label('Store')
                    // Only active stores can be chosen; an existing client keeps its own store listed even
                    // after that store is deactivated, so editing it does not blank the field.
                    ->options(fn (?Client $record) => self::selectableStorefronts($record))
                    ->in(fn (?Client $record) => self::selectableStorefronts($record)->keys()->all())
                    ->validationMessages(['in' => 'This store is not active. Choose an active store.'])
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->live()
                    // Every module the new store supports starts switched on.
                    ->afterStateUpdated(function ($set, $state) {
                        $set('modules', array_keys(ClientModules::optionsForStorefront($state)));
                        // The toggle below is hidden until the store changes, so its default never
                        // applied: start it on each time the choice changes.
                        $set('move_content', true);
                    })
                    ->helperText('The store (template) this client runs. Two clients may run the same store; they share no data.'),
                // Changing the store of an existing client to one with another template: offer to
                // bring their catalogue along, or it disappears from their site (it stays in admin).
                Toggle::make('move_content')
                    ->label('Move this client\'s content to the new template')
                    ->default(true)
                    ->dehydrated(false)
                    ->visible(fn (?Client $record, $get) => self::switchesTemplate($record, $get('storefront_id')))
                    ->helperText(fn (?Client $record) => $record
                        ? self::moveContentHelp($record)
                        : null)
                    ->columnSpanFull(),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false)
                    ->helperText('Inactive clients cannot sign in and their storefront stops answering.'),
            ]),
            'address' => Section::make('Public address')
                ->description(fn (?Client $record) => $record
                    ? 'Customers reach this client at '.$record->publicUrl().' and its team signs in at '.$record->loginUrl().'. The subdomain needs a wildcard DNS record and certificate, a custom domain needs its DNS pointed here.'
                    : 'The client gets its own subdomain, used for both the store and the client login, as soon as it is saved.')
                ->columns(2)
                ->schema([
                    TextInput::make('domain')->label('Custom domain (optional)')->placeholder('shop.example.com')->maxLength(190)
                        // One host, one client: not another client's or store's domain, and not on the platform's own domain.
                        ->rule(fn (?Client $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                            if (filled($value) && ($problem = Client::domainProblem((string) $value, ignoreClient: $record))) {
                                $fail($problem);
                            }
                        })
                        ->dehydrateStateUsing(fn (?string $state) => Client::normaliseHost($state))
                        ->helperText('Host only, no https://. Must be the client\'s own domain, not one on '.Client::baseDomain().'.'),
                ]),
            'modules' => Section::make('Modules')
                ->description('What this client\'s Client Admin can use. A module switched off disappears for the whole team, the Client Admin included; the locked ones are core to the store and always on; what each Admin Staff member sees within these modules is set by their role.')
                ->schema([
                    CheckboxList::make('modules')
                        ->hiddenLabel()
                        ->options(fn ($get) => ClientModules::optionsForStorefront($get('storefront_id')))
                        // A client nobody has narrowed yet has everything its store supports.
                        // The field hands over [] for a null column, so the record is asked directly.
                        ->formatStateUsing(function (?array $state, $get, ?Client $record) {
                            $available = array_keys(ClientModules::optionsForStorefront($get('storefront_id')));

                            $state = $record && $record->modules === null
                                ? $available
                                : array_values(array_intersect($state ?? [], $available));

                            return array_values(array_unique([...$state, ...ClientModules::coreAmong($available)]));
                        })
                        // Core modules show ticked and locked; they are always on whatever is saved.
                        ->disableOptionWhen(fn (string $value) => ClientModules::isCore($value))
                        ->in(fn ($get) => array_keys(ClientModules::optionsForStorefront($get('storefront_id'))))
                        ->dehydrateStateUsing(fn (?array $state, $get) => array_values(array_unique([
                            ...($state ?? []),
                            ...ClientModules::coreAmong(array_keys(ClientModules::optionsForStorefront($get('storefront_id')))),
                        ])))
                        ->default([])
                        ->columns(3)
                        ->bulkToggleable(),
                ]),
            'plan' => Section::make('Plan & limits')
                ->description('The plan sets the defaults; a value entered here overrides it for this client only. Blank means "use the plan", and 0 means unlimited.')
                ->columns(2)
                ->schema([
                    Select::make('plan_id')
                        ->label('Plan')
                        ->options(fn () => Plan::orderBy('position')->get()->mapWithKeys(fn (Plan $plan) => [$plan->id => $plan->label]))
                        ->default(fn () => Plan::default()?->id)
                        ->native(false)
                        ->helperText('Pricing is not charged yet; the plan carries the allowances.'),
                    TextInput::make('image_limit')->label('Product images')->numeric()->minValue(0)->maxValue(1000000)
                        ->placeholder(fn (?Client $record) => 'Plan default ('.($record?->plan?->image_limit ?? config('clients.default_image_limit')).')')
                        ->helperText(fn (?Client $record) => $record ? 'Currently using '.ClientQuota::for($record)->imageCount().' images.' : 'Default is '.config('clients.default_image_limit').' per client.'),
                    TextInput::make('product_limit')->label('Maximum products')->numeric()->minValue(0)->maxValue(1000000)->placeholder('Plan default')
                        ->helperText(fn (?Client $record) => $record ? 'Currently using '.ClientQuota::for($record)->productCount().' products.' : null),
                    TextInput::make('storage_limit_mb')->label('Storage allowance (MB)')->numeric()->minValue(0)->maxValue(1048576)->placeholder('Plan default')
                        ->helperText(fn (?Client $record) => $record ? 'Currently using '.ClientQuota::formatBytes(ClientQuota::for($record)->storageUsed()).' of uploaded media.' : null),
                ]),
            'database' => Section::make('Database')
                ->description(fn (?Client $record) => app(ClientDatabase::class)->isolated()
                    ? 'This installation gives every client its own database. It is created and filled with its store\'s content automatically when the client is saved; if that ever fails, the client\'s page offers "Repair database".'
                    : 'This installation keeps all clients in one database (CLIENT_ISOLATION=shared); rows are separated by client.')
                ->columns(2)
                ->schema([
                    TextInput::make('database')->label('Database name')->maxLength(64)
                        // One database per client: never one another client (or the platform) already uses.
                        ->rule(fn (?Client $record, $get) => new AvailableClientDatabase($record, $get('subdomain')))
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? trim($state) : null)
                        ->placeholder(fn (?Client $record) => $record ? $record->databaseName() : config('clients.prefix', 'client_').'<subdomain>')
                        ->helperText('Leave blank to use the generated name. Only change it when the database already exists under another name.'),
                    Toggle::make('sample_products')->label('Add sample products and services')->default(false)->inline(false)
                        ->dehydrated(false)
                        ->visible(fn (?Client $record) => $record === null && app(ClientDatabase::class)->isolated())
                        ->helperText('Fills the store with its demo products, categories and collections, or services, areas and projects, which the client can edit or delete later. Menus and pages are added either way.'),
                ]),
        ];
    }

    /**
     * Stores a client can be put on: the active ones, plus the client's current store.
     *
     * @return Collection<int, string> id => name
     */
    public static function selectableStorefronts(?Client $record): Collection
    {
        return Storefront::query()
            ->where(fn ($q) => $q->where('is_active', true)
                ->when($record?->storefront_id, fn ($q, $id) => $q->orWhere('id', $id)))
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /** True when an existing client is being moved to a store that runs a different template. */
    public static function switchesTemplate(?Client $record, mixed $storefrontId): bool
    {
        if (! $record || blank($storefrontId) || (int) $storefrontId === (int) $record->storefront_id) {
            return false;
        }
        $to = Storefront::find($storefrontId)?->template;

        return $to !== null && $to !== $record->templateId();
    }

    private static function moveContentHelp(Client $record): string
    {
        $from = $record->templateId();
        $count = $from ? ClientTemplateSwitch::countContent($record, $from) : 0;

        return "Their products, categories, collections, pages, posts and other content ({$count} "
            .Str::plural('item', $count).') were made for the current template and only show there. '
            .'On: they move to the new template and stay on the site. Off: they disappear from the site but stay in admin. '
            .'Either way the new template\'s homepage, menus and Appearance settings start from its defaults.';
    }
}
