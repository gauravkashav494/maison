<?php

namespace App\Filament\Resources\PlatformActivities;

use App\Contracts\AdminIdentity;
use App\Filament\Support\ActivityTable;
use App\Models\PlatformActivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who did what across the platform: account and platform records, and everything client staff
 * do in their own panel. Read-only; each line reads as a sentence (ActivityTable).
 * A Super Admin sees everyone's activity; platform staff see only their own.
 */
class PlatformActivityResource extends Resource
{
    protected static ?string $model = PlatformActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    /**
     * Platform staff only ever see what they did themselves. A Super Admin may see every line;
     * the list page opens on their own and its header button switches to everyone's.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = filament()->auth()->user();

        if ($user instanceof AdminIdentity && $user->isSuperAdmin()) {
            return $query;
        }

        return $user instanceof Model ? $query->byActor($user) : $query->whereRaw('1 = 0');
    }

    public static function getNavigationLabel(): string
    {
        $user = filament()->auth()->user();

        return $user instanceof AdminIdentity && ! $user->isSuperAdmin() ? 'My activity' : 'Activity log';
    }

    public static function table(Table $table): Table
    {
        return ActivityTable::configure(
            $table,
            showActor: (bool) filament()->auth()->user()?->isSuperAdmin(),
            areas: fn () => static::getEloquentQuery()->whereNotNull('area')->distinct()->orderBy('area')->pluck('area', 'area')->all(),
            showClient: true,
            extraFilters: [SelectFilter::make('client_id')->label('Client')->relationship('client', 'name')->searchable()],
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityTable::infolist($schema);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPlatformActivities::route('/')];
    }
}
