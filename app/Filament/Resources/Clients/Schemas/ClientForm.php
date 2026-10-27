<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Clients\ClientDatabase;
use App\Clients\ClientQuota;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Storefront;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Client')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120)->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, $get, $state) => blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true)
                    ->disabled(fn (?Client $record) => $record !== null)
                    ->dehydrated()
                    ->helperText(fn (?Client $record) => $record
                        ? 'Fixed: the database and the public address are named after it.'
                        : 'Used for the subdomain and the database name, and cannot be changed later.'),
                Select::make('storefront_id')
                    ->label('Store')
                    ->options(fn () => Storefront::orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->helperText('The store (template) this client runs. Two clients may run the same store; they share no data.'),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false)
                    ->helperText('Inactive clients cannot sign in and their storefront stops answering.'),
            ]),
            Section::make('Public address')
                ->description(fn (?Client $record) => $record
                    ? 'Customers reach this client at '.$record->publicUrl().'. The entry link '.$record->entryUrl().' always works; the subdomain needs a wildcard DNS record and certificate, a custom domain needs its DNS pointed here.'
                    : 'The client gets its own subdomain and an entry link as soon as it is saved.')
                ->columns(2)
                ->schema([
                    TextInput::make('domain')->label('Custom domain (optional)')->placeholder('shop.example.com')->maxLength(190)->unique(ignoreRecord: true)
                        ->rule('regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i')->helperText('Host only, no https://.'),
                ]),
            Section::make('Plan & limits')
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
            Section::make('Database')
                ->description(fn (?Client $record) => app(ClientDatabase::class)->isolated()
                    ? 'This installation gives every client its own database. Run "php artisan clients:provision '.($record->slug ?? '<slug>').'" once to create it and fill it with the content of its store.'
                    : 'This installation keeps all clients in one database (CLIENT_ISOLATION=shared); rows are separated by client.')
                ->columns(2)
                ->schema([
                    TextInput::make('database')->label('Database name')->maxLength(64)
                        ->placeholder(fn (?Client $record) => $record ? $record->databaseName() : config('clients.prefix', 'client_').'<slug>')
                        ->helperText('Leave blank to use the generated name. Only change it when the database already exists under another name.'),
                ]),
        ]);
    }
}
