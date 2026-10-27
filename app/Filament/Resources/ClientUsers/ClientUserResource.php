<?php

namespace App\Filament\Resources\ClientUsers;

use App\Clients\ClientContext;
use App\Models\Client;
use App\Models\ClientUser;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Staff accounts of a client: Client Admin (everything, including these accounts) and Admin
 * Staff (day-to-day work). Registered in both panels — the operator manages every client's
 * staff on /admin, a Client Admin manages its own on /client.
 *
 * The query and the form are both tied to the client resolved for the request, so a staff
 * account can never be listed, created or moved into another client. ClientUserPolicy
 * repeats the check per record.
 */
class ClientUserResource extends Resource
{
    protected static ?string $model = ClientUser::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Team';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'staff account';

    /** Only the resolved client's accounts; a platform operator working across clients sees all. */
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
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('email')->email()->required()->maxLength(190)
                    // Addresses are unique inside a client, not across the platform.
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(ClientContext::class)->id() ?? 0)),
                TextInput::make('phone')->maxLength(30),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->minLength(8)
                    ->helperText(fn (string $operation) => $operation === 'create' ? 'Minimum 8 characters.' : 'Leave blank to keep the current password.'),
            ]),
            Section::make('Role')->columns(2)->schema([
                Select::make('role')
                    ->options(ClientUser::ROLES)
                    ->default(ClientUser::ROLE_ADMIN_STAFF)
                    ->required()
                    ->native(false)
                    ->disabled(fn (?ClientUser $record) => $record && $record->is(filament()->auth()->user()))
                    ->helperText('A Client Admin can manage these staff accounts; Admin Staff cannot.'),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false)
                    ->disabled(fn (?ClientUser $record) => $record && $record->is(filament()->auth()->user()))
                    ->helperText('A disabled account cannot sign in.'),
                Select::make('client_id')
                    ->label('Client')
                    ->options(fn () => Client::orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->native(false)
                    ->searchable()
                    // Only the operator working across clients may choose; otherwise it is fixed.
                    ->visible(fn () => $clients->isSuperAdmin() && ! $clients->id())
                    ->default(fn () => $clients->id()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (ClientUser $r) => $r->email),
                TextColumn::make('client.name')->label('Client')->badge()->color('gray')->sortable()
                    ->visible(fn () => app(ClientContext::class)->isSuperAdmin()),
                TextColumn::make('role')->badge()
                    ->formatStateUsing(fn (?string $state) => ClientUser::ROLES[$state] ?? $state)
                    ->color(fn (?string $state) => $state === ClientUser::ROLE_CLIENT_ADMIN ? 'info' : 'gray'),
                TextColumn::make('phone')->placeholder('—')->color('gray')->toggleable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Added')->date('d M Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(ClientUser::ROLES),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientUsers::route('/'),
            'create' => Pages\CreateClientUser::route('/create'),
            'edit' => Pages\EditClientUser::route('/{record}/edit'),
        ];
    }
}
