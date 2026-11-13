<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Clients\ClientDatabase;
use App\Clients\ClientModules;
use App\Clients\ClientQuota;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Storefront;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
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
                ->description('What their team can use')
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
                    ->options(fn () => Storefront::orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->live()
                    // Every module the new store supports starts switched on.
                    ->afterStateUpdated(fn ($set, $state) => $set('modules', array_keys(ClientModules::optionsForStorefront($state))))
                    ->helperText('The store (template) this client runs. Two clients may run the same store; they share no data.'),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false)
                    ->helperText('Inactive clients cannot sign in and their storefront stops answering.'),
            ]),
            'address' => Section::make('Public address')
                ->description(fn (?Client $record) => $record
                    ? 'Customers reach this client at '.$record->publicUrl().' and its team signs in at '.$record->loginUrl().'. The subdomain needs a wildcard DNS record and certificate, a custom domain needs its DNS pointed here.'
                    : 'The client gets its own subdomain, used for both the store and the client login, as soon as it is saved.')
                ->columns(2)
                ->schema([
                    TextInput::make('domain')->label('Custom domain (optional)')->placeholder('shop.example.com')->maxLength(190)->unique(ignoreRecord: true)
                        ->rule('regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i')->helperText('Host only, no https://.'),
                ]),
            'modules' => Section::make('Modules')
                ->description('What this client can use. A module switched off disappears from their panel, for every member of their team.')
                ->schema([
                    CheckboxList::make('modules')
                        ->hiddenLabel()
                        ->options(fn ($get) => ClientModules::optionsForStorefront($get('storefront_id')))
                        // A client nobody has narrowed yet has everything its store supports.
                        ->formatStateUsing(function (?array $state, $get) {
                            $available = array_keys(ClientModules::optionsForStorefront($get('storefront_id')));

                            return $state === null ? $available : array_values(array_intersect($state, $available));
                        })
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
                        ->placeholder(fn (?Client $record) => $record ? $record->databaseName() : config('clients.prefix', 'client_').'<subdomain>')
                        ->helperText('Leave blank to use the generated name. Only change it when the database already exists under another name.'),
                    Toggle::make('sample_products')->label('Add sample products and services')->default(false)->inline(false)
                        ->dehydrated(false)
                        ->visible(fn (?Client $record) => $record === null && app(ClientDatabase::class)->isolated())
                        ->helperText('Fills the store with its demo products, categories and collections, or services, areas and projects, which the client can edit or delete later. Menus and pages are added either way.'),
                ]),
        ];
    }
}
