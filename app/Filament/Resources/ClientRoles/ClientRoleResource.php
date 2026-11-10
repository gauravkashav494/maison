<?php

namespace App\Filament\Resources\ClientRoles;

use App\Clients\ClientContext;
use App\Clients\ClientModules;
use App\Models\ClientRole;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Roles a Client Admin builds for their own staff, on the client panel only. The form offers
 * only the modules switched on for the client, and the query is tied to the resolved client.
 */
class ClientRoleResource extends Resource
{
    protected static ?string $model = ClientRole::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Team';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Roles';

    protected static ?string $modelLabel = 'role';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('client_id', app(ClientContext::class)->id() ?? 0);
    }

    /** @return array<string, array> modules of the current client that can be granted */
    private static function grantableModules(): array
    {
        $client = app(ClientContext::class)->client();

        return $client
            ? array_filter(ClientModules::forClient($client), fn (string $key) => $client->hasModule($key), ARRAY_FILTER_USE_KEY)
            : [];
    }

    public static function form(Schema $schema): Schema
    {
        $sections = collect(self::grantableModules())
            ->groupBy('group', preserveKeys: true)
            ->map(fn ($modules, string $group) => Section::make($group)->columns(2)->schema(
                $modules->map(fn (array $module, string $key) => CheckboxList::make('grid.'.$key)
                    ->label($module['label'])
                    ->options(ClientModules::actionOptions($key))
                    ->columns(4))->values()->all()
            ))
            ->values()
            ->all();

        return $schema->columns(1)->components([
            Section::make('Role')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(80)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(ClientContext::class)->id())),
                TextInput::make('description')->maxLength(255),
            ]),
            ...$sections,
        ]);
    }

    /** Split the stored list into one checkbox group per module. */
    public static function toGrid(?array $permissions): array
    {
        $grid = [];
        foreach ($permissions ?? [] as $permission) {
            [$module, $action] = array_pad(explode('.', $permission, 2), 2, null);
            $grid[$module][] = $action;
        }

        return $grid;
    }

    /** Join the checkbox groups back together, keeping only what this client may grant. */
    public static function fromGrid(?array $grid): array
    {
        $permissions = [];
        foreach (self::grantableModules() as $key => $module) {
            foreach (array_intersect($grid[$key] ?? [], $module['actions']) as $action) {
                $permissions[] = $key.'.'.$action;
            }
        }

        return $permissions;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold')->description(fn (ClientRole $r) => $r->description),
                TextColumn::make('permission_count')->label('Permissions')
                    ->state(fn (ClientRole $r) => count($r->permissions ?? []))->alignRight(),
                TextColumn::make('users_count')->counts('users')->label('Staff')->alignRight(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientRoles::route('/'),
            'create' => Pages\CreateClientRole::route('/create'),
            'edit' => Pages\EditClientRole::route('/{record}/edit'),
        ];
    }
}
