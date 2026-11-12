<?php

namespace App\Filament\Resources\ClientUsers;

use App\Clients\ClientContext;
use App\Filament\Concerns\OpensTrashedRecords;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Support\RowActions;
use App\Models\Client;
use App\Models\ClientRole;
use App\Models\ClientUser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Staff accounts of a client: Client Admin (everything, including these accounts) and Admin
 * Staff (whatever their role grants). Registered in both panels — a SuperAdmin creates and
 * maintains each client's Client Admin on /admin, a Client Admin manages its own team on /client.
 *
 * The query and the form are both tied to the client resolved for the request, so a staff
 * account can never be listed, created or moved into another client. ClientUserPolicy
 * repeats the check per record.
 */
class ClientUserResource extends Resource
{
    use OpensTrashedRecords;

    protected static ?string $model = ClientUser::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Team';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'staff account';

    /** On /admin this screen is the clients' main accounts only; their other staff are their own. */
    public static function getNavigationLabel(): string
    {
        return app(ClientContext::class)->isSuperAdmin() ? 'Client admins' : 'Staff';
    }

    /**
     * A SuperAdmin manages each client's admins on the client itself (Clients → Edit → Client
     * admins), so the menu entry is hidden on /admin. The routes stay, for links from a client.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return ! app(ClientContext::class)->isSuperAdmin() && parent::shouldRegisterNavigation();
    }

    public static function getNavigationGroup(): ?string
    {
        return app(ClientContext::class)->isSuperAdmin() ? 'Clients' : 'Team';
    }

    public static function getModelLabel(): string
    {
        return app(ClientContext::class)->isSuperAdmin() ? 'client admin' : 'staff account';
    }

    /** Only the resolved client's accounts; a SuperAdmin sees each client's Client Admins, not their staff. */
    public static function getEloquentQuery(): Builder
    {
        $clients = app(ClientContext::class);
        $query = parent::getEloquentQuery();

        return $clients->isSuperAdmin()
            ? $query->where('role', ClientUser::ROLE_CLIENT_ADMIN)
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
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('client_id', app(ClientContext::class)->id() ?? $get('client_id') ?? 0)),
                TextInput::make('phone')->maxLength(30),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->minLength(8)
                    ->hint('Minimum 8 characters')
                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Use at least 8 characters. A mix of letters, numbers and symbols is stronger.')
                    ->helperText(fn (string $operation) => $operation === 'create' ? null : 'Leave blank to keep the current password.'),
            ]),
            Section::make('Access')->columns(2)->schema([
                Select::make('role')->label('Account type')
                    ->options(ClientUser::ROLES)
                    ->default(ClientUser::ROLE_ADMIN_STAFF)
                    ->required()
                    ->native(false)
                    ->live()
                    ->visible(fn () => ! $clients->isSuperAdmin())
                    ->disabled(fn (?ClientUser $record) => $record && $record->is(filament()->auth()->user()))
                    ->helperText('A Client Admin can do everything, including managing staff. Admin Staff can only do what the role below allows.'),
                Select::make('client_role_id')
                    ->label('Role')
                    ->options(fn () => ClientRole::where('client_id', $clients->id() ?? 0)->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn ($get) => ! $clients->isSuperAdmin() && $get('role') === ClientUser::ROLE_ADMIN_STAFF)
                    ->required(fn ($get) => ! $clients->isSuperAdmin() && $get('role') === ClientUser::ROLE_ADMIN_STAFF)
                    // Only a role of this client: an id from another client is refused.
                    ->rule(fn () => Rule::exists('client_roles', 'id')->where('client_id', $clients->id() ?? 0))
                    ->native(false)
                    ->helperText('Only for Admin Staff. Roles are managed under Team → Roles.'),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false)
                    ->disabled(fn (?ClientUser $record) => $record && $record->is(filament()->auth()->user()))
                    ->helperText('A disabled account cannot sign in.'),
                Select::make('client_id')
                    ->label('Client')
                    ->options(fn () => Client::orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->native(false)
                    ->searchable()
                    // Only a SuperAdmin may choose, and only when creating; otherwise it is fixed.
                    ->visible(fn () => $clients->isSuperAdmin())
                    ->disabled(fn (?ClientUser $record) => $record !== null),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $superAdmin = fn () => app(ClientContext::class)->isSuperAdmin();

        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Account')->columns(2)->schema([
                    TextEntry::make('name')->weight('bold'),
                    TextEntry::make('email')->copyable()->icon('heroicon-m-envelope'),
                    TextEntry::make('phone')->placeholder('—')->copyable()->icon('heroicon-m-phone'),
                ]),
                Section::make('Access')->columns(2)->schema([
                    TextEntry::make('role')->label('Account type')->badge()
                        ->formatStateUsing(fn (?string $state) => ClientUser::ROLES[$state] ?? $state)
                        ->color(fn (ClientUser $r) => $r->isClientAdmin() ? 'info' : 'gray'),
                    TextEntry::make('clientRole.name')->label('Role')
                        ->state(fn (ClientUser $r) => $r->isClientAdmin() ? 'Everything, including managing staff' : ($r->clientRole?->name ?? 'No role — cannot do anything yet'))
                        ->color(fn (ClientUser $r) => ! $r->isClientAdmin() && ! $r->clientRole ? 'danger' : null),
                ]),
                Section::make('Client')->columns(2)->visible($superAdmin)->schema([
                    TextEntry::make('client.name')->label('Client')->weight('bold')
                        ->url(fn (ClientUser $r) => $r->client ? ClientResource::getUrl('view', ['record' => $r->client]) : null)
                        ->color('primary')
                        ->helperText(fn (ClientUser $r) => $r->client?->storefront?->name),
                    ViewEntry::make('login_url')->label('Signs in at')
                        ->state(fn (ClientUser $r) => $r->client?->loginUrl())
                        ->view('filament.tables.columns.link-copy')
                        ->viewData(['inline' => true, 'icon' => 'heroicon-m-arrow-right-end-on-rectangle']),
                ]),
            ]),
            Grid::make(1)->columnSpan(1)->schema([
                Section::make('Status')->schema([
                    IconEntry::make('is_active')->label('Can sign in')->boolean(),
                    TextEntry::make('email_verified_at')->label('Email verified')->dateTime('d M Y')->placeholder('Not verified'),
                    TextEntry::make('created_at')->label('Added')->dateTime('d M Y, H:i'),
                    TextEntry::make('updated_at')->label('Last updated')->since(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (ClientUser $r) => $r->email),
                TextColumn::make('client.name')->label('Client')->badge()->color('gray')->sortable()
                    ->visible(fn () => app(ClientContext::class)->isSuperAdmin()),
                TextColumn::make('role')->label('Access')->badge()
                    ->formatStateUsing(fn (?string $state, ClientUser $r) => $r->isClientAdmin() ? ClientUser::ROLES[$state] : ($r->clientRole?->name ?? 'No role'))
                    ->color(fn (?string $state, ClientUser $r) => $r->isClientAdmin() ? 'info' : ($r->clientRole ? 'gray' : 'danger')),
                TextColumn::make('phone')->placeholder('—')->color('gray')->toggleable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Added')->date('d M Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Account type')->options(ClientUser::ROLES)->visible(fn () => ! app(ClientContext::class)->isSuperAdmin()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                RowActions::group([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('toggle')->label(fn (ClientUser $r) => $r->is_active ? 'Deactivate' : 'Activate')
                        ->icon(fn (ClientUser $r) => $r->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                        ->color(fn (ClientUser $r) => $r->is_active ? 'danger' : 'success')
                        // Nobody switches off their own account, and only someone allowed to edit it may toggle it.
                        // and a client always keeps one active Client Admin (the same rule as deleting).
                        ->visible(fn (ClientUser $r) => ! $r->trashed() && ! $r->is(filament()->auth()->user()) && static::canEdit($r)
                            && ! ($r->is_active && $r->isClientAdmin() && ClientUser::where('client_id', $r->client_id)
                                ->where('role', ClientUser::ROLE_CLIENT_ADMIN)->where('is_active', true)->count() <= 1))
                        ->requiresConfirmation()
                        ->action(fn (ClientUser $r) => $r->update(['is_active' => ! $r->is_active])),
                    ...RowActions::trash(),
                ]),
            ])
            ->toolbarActions([BulkActionGroup::make(RowActions::trashBulk())]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientUsers::route('/'),
            'create' => Pages\CreateClientUser::route('/create'),
            'view' => Pages\ViewClientUser::route('/{record}'),
            'edit' => Pages\EditClientUser::route('/{record}/edit'),
        ];
    }
}
