<?php

namespace App\Filament\Support;

use App\Models\PlatformActivity;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * The activity log as a table, shared by the client panel's Activity log, the platform's
 * Activity log and the "History" tab on a record. Each row reads as a sentence:
 *
 *   Priya Sharma changed the price of product "Silk Saree" from ₹4,500.00 to ₹3,999.00
 *
 * The ⋮ menu opens the full line, with every changed field and its before and after values.
 */
class ActivityTable
{
    public const ACTIONS = [
        'created' => 'Added',
        'updated' => 'Changed',
        'deleted' => 'Moved to trash',
        'restored' => 'Restored',
        'deleted permanently' => 'Deleted permanently',
        'signed in' => 'Signed in',
        'signed out' => 'Signed out',
        'sign-in failed' => 'Failed sign-in',
        'password reset' => 'Password reset',
        'downloaded' => 'Downloaded',
        'viewed' => 'Opened',
    ];

    /**
     * @param  bool  $showActor  false when every row is the viewer's own
     * @param  Closure(): array<string, string>|null  $people  actor_id => name, for the "Who" filter
     * @param  Closure(): array<string, string>|null  $areas  the areas present, for the "Area" filter
     * @param  array<BaseFilter>  $extraFilters  shown first
     */
    public static function configure(Table $table, bool $showActor = true, ?Closure $people = null, ?Closure $areas = null, bool $showClient = false, bool $showArea = true, array $extraFilters = []): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i')->sortable()->width('10rem'),
                TextColumn::make('actor')->label('Who')->visible($showActor)->searchable()
                    ->description(fn (PlatformActivity $r) => $r->actor_type === 'Client staff' ? null : $r->actor_type)
                    ->placeholder('System'),
                TextColumn::make('activity')->label('What happened')->wrap()
                    ->state(fn (PlatformActivity $r) => self::sentence($r, false))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('actor', 'like', "%{$search}%"))),
                TextColumn::make('action')->label('Action')->badge()
                    ->formatStateUsing(fn (string $state) => self::ACTIONS[$state] ?? ucfirst($state))
                    ->color(fn (string $state) => self::actionColor($state)),
                TextColumn::make('area')->badge()->color('gray')->placeholder('—')->visible($showArea),
                TextColumn::make('client.name')->label('Client')->placeholder('—')->visible($showClient),
                TextColumn::make('ip')->label('IP address')->placeholder('—')->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(array_values(array_filter([
                ...$extraFilters,
                $people && $showActor ? SelectFilter::make('actor_id')->label('Who')->options($people)->searchable() : null,
                $areas && $showArea ? SelectFilter::make('area')->options($areas)->searchable() : null,
                SelectFilter::make('action')->label('What')->options(self::ACTIONS)->searchable(),
                self::dateFilter(),
            ])))
            ->recordActions([RowActions::group([
                ViewAction::make()->label('Details')->modalHeading('Activity details')->modalWidth('2xl'),
            ])])
            ->striped()
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('Changes made in this panel show up here.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    /** The detail view: the sentence, when and where, and every changed field. */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('activity')->hiddenLabel()->columnSpanFull()->size('lg')
                ->state(fn (PlatformActivity $r) => self::sentence($r, true)),
            Grid::make(3)->columnSpanFull()->schema([
                TextEntry::make('created_at')->label('When')->dateTime('d M Y, H:i:s'),
                TextEntry::make('area')->placeholder('—'),
                TextEntry::make('ip')->label('IP address')->placeholder('—'),
            ]),
            RepeatableEntry::make('changes')->label('What changed')->columnSpanFull()
                ->visible(fn (PlatformActivity $r) => filled($r->changes))
                ->state(fn (PlatformActivity $r) => self::rows($r))
                ->table([
                    TableColumn::make('Field'),
                    TableColumn::make('Before'),
                    TableColumn::make('After'),
                ])
                ->schema([
                    TextEntry::make('label'),
                    TextEntry::make('old')->placeholder('—'),
                    TextEntry::make('new')->placeholder('—'),
                ]),
        ]);
    }

    /** "<b>Priya Sharma</b> changed …", or the sentence alone when the viewer is the actor. */
    public static function sentence(PlatformActivity $r, bool $withActor): HtmlString
    {
        $sentence = e(ucfirst($r->sentence()));

        return new HtmlString($withActor ? '<strong>'.e($r->actor).'</strong> '.e($r->sentence()) : $sentence);
    }

    private static function actionColor(string $action): string
    {
        return match ($action) {
            'created', 'restored' => 'success',
            'updated', 'viewed' => 'info',
            'deleted', 'password reset' => 'warning',
            'deleted permanently', 'sign-in failed' => 'danger',
            default => 'gray',
        };
    }

    /** The changes as table rows, ready to show. */
    public static function rows(PlatformActivity $r): array
    {
        return array_map(fn (array $change) => [
            'label' => ucfirst((string) ($change['label'] ?? $change['field'] ?? '')),
            'old' => self::value($change, 'old'),
            'new' => self::value($change, 'new'),
        ], $r->changes ?? []);
    }

    /** A change's before/after cell: hidden values say so instead of showing a blank. */
    private static function value(array $change, string $side): ?string
    {
        if (($change['field'] ?? null) === 'record') {
            return $side === 'new' ? ucfirst((string) $change['new']) : null;
        }

        if ($change[$side] === null && ($change['old'] ?? null) === null && ($change['new'] ?? null) === null) {
            return 'not shown';
        }

        return $change[$side] === '' ? 'empty' : trim((string) $change[$side], '"');
    }

    private static function dateFilter(): DateRangeFilter
    {
        return DateRangeFilter::range('when', 'When');
    }
}
