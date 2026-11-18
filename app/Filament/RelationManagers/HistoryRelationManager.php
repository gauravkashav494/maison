<?php

namespace App\Filament\RelationManagers;

use App\Filament\Support\ActivityTable;
use App\Models\ClientUser;
use App\Models\PlatformActivity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "History" tab on a record's page: every activity-log line about this record, newest first
 * ("Priya Sharma marked order #1043 as Dispatched"). Shown to whoever may open the record.
 * In the client panel it lists the client's own staff only, like the Activity log does.
 */
class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'History';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clock';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return method_exists($ownerRecord, static::$relationship) && (bool) filament()->auth()->user()?->can('viewAny', PlatformActivity::class);
    }

    public function table(Table $table): Table
    {
        $user = filament()->auth()->user();

        return ActivityTable::configure($table, showArea: false, showClient: false)
            ->modifyQueryUsing(fn (Builder $query) => $user instanceof ClientUser
                ? $query->forClientStaff((int) $user->client_id)
                : $query)
            ->heading('History');
    }

    public function infolist(Schema $schema): Schema
    {
        return ActivityTable::infolist($schema);
    }
}
