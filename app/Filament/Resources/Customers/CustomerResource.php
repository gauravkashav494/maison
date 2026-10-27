<?php

namespace App\Filament\Resources\Customers;

use App\Clients\ClientContext;
use App\Models\Client;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                TextInput::make('email')->email()->required()->maxLength(190)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(ClientContext::class)->id() ?? 0)),
                TextInput::make('phone')->maxLength(30),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn ($state) => filled($state))
                    ->minLength(8)
                    ->helperText('Leave blank to keep the current password.'),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false)
                    ->helperText('A disabled customer cannot sign in to the storefront.'),
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (Customer $r) => $r->email),
                TextColumn::make('client.name')->label('Client')->badge()->color('gray')->sortable()
                    ->visible(fn () => app(ClientContext::class)->isSuperAdmin()),
                TextColumn::make('phone')->placeholder('—')->color('gray')->searchable()->toggleable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Joined')->date('d M Y')->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
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
        ];
    }
}
