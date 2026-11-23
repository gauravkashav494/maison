<?php

namespace App\Filament\Resources\ActivityLog;

use App\Filament\Support\ActivityTable;
use App\Models\ClientUser;
use App\Models\PlatformActivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The client panel's activity log: what the client's own staff did, one sentence per line
 * ("Priya Sharma marked order #1043 as Dispatched"). Read-only.
 *
 * A Client Admin sees every staff member's lines; other staff see only their own ("My activity").
 * Only lines written by the client's staff accounts are listed — never another client's, and
 * not the platform team's work on the account.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = PlatformActivity::class;

    protected static ?string $slug = 'activity-log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Team';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    public static function getEloquentQuery(): Builder
    {
        $user = filament()->auth()->user();
        $query = parent::getEloquentQuery();

        if (! $user instanceof ClientUser || ! $user->client_id) {
            return $query->whereRaw('1 = 0');
        }

        $query->forClientStaff($user->client_id);

        return $user->isClientAdmin() ? $query : $query->byActor($user);
    }

    public static function getNavigationLabel(): string
    {
        return self::seesEveryone() ? 'Activity log' : 'My activity';
    }

    public static function getPluralModelLabel(): string
    {
        return self::seesEveryone() ? 'activity log' : 'my activity';
    }

    public static function table(Table $table): Table
    {
        return ActivityTable::configure(
            $table,
            showActor: self::seesEveryone(),
            people: fn () => ClientUser::withTrashed()
                ->where('client_id', filament()->auth()->user()?->client_id)
                ->orderBy('name')->pluck('name', 'id')->all(),
            areas: fn () => static::getEloquentQuery()->whereNotNull('area')->distinct()->orderBy('area')->pluck('area', 'area')->all(),
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityTable::infolist($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLog::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }

    private static function seesEveryone(): bool
    {
        $user = filament()->auth()->user();

        return $user instanceof ClientUser && $user->isClientAdmin();
    }
}
