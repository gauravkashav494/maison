<?php

namespace App\Filament\Resources\Customers;

use App\Clients\ClientContext;
use App\Filament\Concerns\OpensTrashedRecords;
use App\Filament\Forms\Components\PhoneInput;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Customers\Schemas\CustomerInfolist;
use App\Filament\Support\DateRangeFilter;
use App\Filament\Support\PasswordHintColumn;
use App\Filament\Support\RowActions;
use App\Models\Client;
use App\Models\Customer;
use App\Support\CustomerPhone;
use App\Support\PasswordRules;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shoppers of one client. Customers live in the platform database and are scoped by client,
 * so the same person shopping with two clients is two accounts and neither client sees the
 * other's. The query below is tied to the resolved client; CustomerPolicy repeats the check
 * per record.
 */
class CustomerResource extends Resource
{
    use OpensTrashedRecords;

    protected static ?string $model = Customer::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $clients = app(ClientContext::class);
        $query = parent::getEloquentQuery();

        return $clients->isSuperAdmin() && ! $clients->id()
            ? $query
            : $query->where('client_id', $clients->id() ?? 0);
    }

    public static function form(Schema $schema): Schema
    {
        $clients = app(ClientContext::class);

        return $schema->columns(1)->components([
            Section::make('Customer')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                PhoneInput::make('phone')
                    ->required()
                    // One customer per phone number within a client, whatever shape it was saved in.
                    ->rule(fn (Get $get, ?Customer $record) => CustomerPhone::uniqueRule(
                        fn () => $get('client_id') ?: $clients->id(),
                        $record?->getKey(),
                        'Another customer of this store already has this phone number.',
                    ))
                    // Customer sign-in uses national digits, so keep the stored customer phone
                    // in that same canonical form even though the input displays +91.
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? PhoneNumber::national('IN', $state) : null),
                // Optional: a customer may be known by phone only (they sign in with their phone). Without
                // an email they get no order emails and cannot reset a forgotten password.
                TextInput::make('email')->email()->maxLength(190)
                    // Saved in lower case and unique within the client whatever its case, also across
                    // its deleted customers (as the database key is), like the storefront's checks.
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtolower(trim($state)) : null)
                    ->rule(fn (Get $get, ?Customer $record) => function (string $attribute, mixed $value, \Closure $fail) use ($get, $record, $clients): void {
                        $clientId = $get('client_id') ?: $clients->id();
                        if ($clientId && filled($value) && Customer::withTrashed()->where('client_id', $clientId)
                            ->whereRaw('lower(email) = ?', [strtolower(trim((string) $value))])
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists()) {
                            $fail('Another customer of this store already has this email address.');
                        }
                    })
                    ->helperText('Optional. Needed to receive order emails and to reset a forgotten password.'),
                PasswordRules::field(TextInput::make('password'), 'Leave blank to keep the current password. Sales people need a password to sign in.')
                    ->required(fn (Get $get, ?Customer $record) => (bool) $get('is_salesperson') && blank($record?->password))
                    ->dehydrated(fn ($state) => filled($state)),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false)
                    ->helperText('A disabled customer cannot sign in to the storefront.'),
                Toggle::make('is_salesperson')->label('Sales person')->default(false)->inline(false)
                    ->helperText('Signs in on the store and checks out without a delivery address, for themselves or for a customer. The store\'s address goes on the order.'),
                Select::make('client_id')
                    ->label('Client')
                    ->options(fn () => Client::orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->visible(fn () => $clients->isSuperAdmin() && ! $clients->id())
                    ->default(fn () => $clients->id()),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [OrdersRelationManager::class];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (Customer $r) => $r->email),
                TextColumn::make('client.name')->label('Client')->badge()->color('gray')->sortable()
                    ->visible(fn () => app(ClientContext::class)->isSuperAdmin()),
                TextColumn::make('phone')->placeholder('—')->color('gray')->searchable()->toggleable(),
                // Guests were created from an order or booking: no password and no phone proven by a WhatsApp code.
                TextColumn::make('account')->label('Account')->badge()
                    ->state(fn (Customer $r) => match (true) {
                        $r->isSalesperson() => 'Sales person',
                        $r->isAccount() => 'Registered',
                        default => 'Guest',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Guest' => 'gray',
                        'Sales person' => 'info',
                        default => 'success',
                    }),
                PasswordHintColumn::make(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Joined')->date('d M Y')->sortable(),
            ])
            ->filters([
                // When the customer joined: one picker with ready ranges, both days included.
                DateRangeFilter::range('joined', 'Joined',
                    scope: fn (Builder $query, string $from, string $until) => $query->joinedBetween($from, $until)),
                TernaryFilter::make('is_salesperson')->label('Account type')
                    ->trueLabel('Sales people')->falseLabel('Customers'),
                TrashedFilter::make(),
            ])
            ->recordActions([RowActions::group([ViewAction::make(), EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make(RowActions::trashBulk())]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}
