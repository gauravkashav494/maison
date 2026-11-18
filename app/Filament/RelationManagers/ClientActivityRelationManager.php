<?php

namespace App\Filament\RelationManagers;

use App\Filament\Support\ActivityTable;
use App\Models\PlatformActivity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "Activity" tab on a client's page in /admin: everything done in or to that client — its
 * staff's work in their panel and the platform team's changes to the account. Super Admins only.
 */
class ClientActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'activity';

    protected static ?string $title = 'Activity';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clipboard-document-list';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) filament()->auth()->user()?->isSuperAdmin();
    }

    public function table(Table $table): Table
    {
        return ActivityTable::configure(
            $table,
            areas: fn () => PlatformActivity::where('client_id', $this->getOwnerRecord()->getKey())
                ->whereNotNull('area')->distinct()->orderBy('area')->pluck('area', 'area')->all(),
        )->modifyQueryUsing(fn (Builder $query) => $query->reorder()->latest('created_at'))
            ->heading('Activity');
    }

    public function infolist(Schema $schema): Schema
    {
        return ActivityTable::infolist($schema);
    }
}
