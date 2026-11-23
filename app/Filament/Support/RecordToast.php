<?php

namespace App\Filament\Support;

use App\Support\Activity\ActivityDescriber;
use App\Support\Activity\ActivityLog;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The toast after a record is created, saved, deleted or restored, naming the record and saying
 * what happened: 'Product "Linen Shirt" updated' / 'Changed: price, stock and variants'.
 *
 * The record is named the way the activity log names it (ActivityDescriber::reference()), and
 * "what changed" is read from the activity lines written during the save (ActivityLog::since()),
 * so the toast and the log always agree.
 *
 * Used by the base Create / Edit pages (App\Filament\Support\Pages) and, for table and modal
 * actions, set once in AppServiceProvider. A page or action with its own message keeps it.
 */
class RecordToast
{
    public static function createdTitle(Model $record): string
    {
        return static::ref($record).' created';
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  activity lines written by the save
     */
    public static function saved(Model $record, array $lines): Notification
    {
        if ($lines === [] && ! $record->wasChanged()) {
            return Notification::make()
                ->info()
                ->title('No changes made')
                ->body(static::ref($record).' is already up to date.');
        }

        return Notification::make()
            ->success()
            ->title(static::savedTitle($record))
            ->body(static::changedBody($record, $lines));
    }

    public static function savedTitle(Model $record): string
    {
        return static::ref($record).' updated';
    }

    /**
     * "Changed: price, stock and variants": the record's own fields first, then the other kinds
     * of record the save touched (its variants, images, …). Null when nothing was logged.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public static function changedBody(Model $record, array $lines): ?string
    {
        $describer = app(ActivityDescriber::class);
        $own = [];
        $others = [];

        foreach ($lines as $line) {
            $isOwn = ($line['subject_type'] ?? null) === class_basename($record)
                && (string) ($line['subject_id'] ?? '') === (string) $record->getKey();

            if ($isOwn) {
                foreach ($line['changes'] ?? [] as $change) {
                    if (filled($change['label'] ?? null)) {
                        $own[] = $change['label'];
                    }
                }

                continue;
            }

            $type = isset($line['subject_class'])
                ? $describer->type($line['subject_class'])
                : strtolower((string) ($line['subject_type'] ?? ''));
            if ($type !== '') {
                $others[$type] = ($others[$type] ?? 0) + 1;
            }
        }

        $parts = array_values(array_unique($own));
        foreach ($others as $type => $count) {
            $parts[] = $count === 1 ? $type : ($type === 'FAQ' ? 'FAQs' : str($type)->plural()->toString());
        }

        return $parts === [] ? null : 'Changed: '.static::list($parts).'.';
    }

    public static function deletedTitle(Model $record): string
    {
        return static::ref($record).' deleted';
    }

    public static function deletedBody(Model $record): string
    {
        return static::softDeletes($record)
            ? 'Moved to the trash, so it can still be restored.'
            : 'This can’t be undone.';
    }

    public static function forceDeletedTitle(Model $record): string
    {
        return static::ref($record).' deleted permanently';
    }

    public static function restoredTitle(Model $record): string
    {
        return static::ref($record).' restored';
    }

    public static function restoredBody(Model $record): string
    {
        return 'It’s back in the '.app(ActivityDescriber::class)->area($record).' list.';
    }

    public static function replicatedTitle(Model $record): string
    {
        return static::ref($record).' duplicated';
    }

    /**
     * "3 products deleted", from the lines a bulk action is holding back. Null when nothing was
     * logged, so the caller keeps Filament's own wording.
     */
    public static function bulkTitle(string $verb, ?string $model = null): ?string
    {
        $lines = static::bulkLines($model);
        if ($lines === []) {
            return null;
        }

        $classes = array_unique(array_column($lines, 'subject_class'));
        $count = count($lines);
        $what = count($classes) === 1
            ? app(ActivityDescriber::class)->pluralType($classes[0], $count)
            : ($count === 1 ? 'record' : 'records');

        return $count.' '.$what.' '.$verb;
    }

    /**
     * "Linen Shirt, Silk Saree and 1 more." and, for a delete, whether it can be undone.
     *
     * @param  string  $verb  as given to bulkTitle(): deleted, deleted permanently, restored
     * @param  class-string<Model>|null  $model  the table's model
     */
    public static function bulkBody(string $verb, ?string $model = null): ?string
    {
        $lines = static::bulkLines($model);
        if ($lines === []) {
            return null;
        }

        $names = array_column($lines, 'subject');
        $shown = array_slice($names, 0, 3);
        $more = count($names) - count($shown);
        $list = $more > 0 ? implode(', ', $shown).' and '.$more.' more' : static::list($shown);

        $note = match (true) {
            $verb === 'deleted' && $lines[0]['action'] === 'deleted' && static::softDeletes($lines[0]['subject_class']) => 'Moved to the trash, so they can still be restored.',
            str_starts_with($verb, 'deleted') => 'This can’t be undone.',
            default => null,
        };

        return $list.'.'.($note ? ' '.$note : '');
    }

    /**
     * The bulk action's lines for the table's own records (not side effects such as the variants
     * deleted with their products), else for the first kind of record logged.
     *
     * @param  class-string<Model>|null  $model  the table's model
     */
    private static function bulkLines(?string $model = null): array
    {
        $lines = array_values(array_filter(
            app(ActivityLog::class)->bulkLines(),
            fn (array $line) => isset($line['subject_class']),
        ));
        if ($lines === []) {
            return [];
        }

        $first = collect($lines)->first(fn (array $line) => $model && is_a($line['subject_class'], $model, true)) ?? $lines[0];

        return array_values(array_filter(
            $lines,
            fn (array $line) => $line['action'] === $first['action'] && $line['subject_class'] === $first['subject_class'],
        ));
    }

    /**
     * The action's record, or once it can no longer be found (deleted permanently, or trashed
     * from a table that hides the trash) the last record the activity log saw this happen to.
     */
    public static function subject(?Model $record, string $action): ?Model
    {
        if ($record) {
            return $record;
        }

        $line = collect(app(ActivityLog::class)->since())->last(fn (array $line) => $line['action'] === $action && isset($line['model']));

        return $line['model'] ?? null;
    }

    /** 'Product "Linen Shirt"', 'Order #1043'. */
    public static function ref(Model $record): string
    {
        return ucfirst(app(ActivityDescriber::class)->reference($record));
    }

    public static function softDeletes(Model|string $record): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($record), true);
    }

    /** "a", "a and b", "a, b, c and 2 more". */
    private static function list(array $parts): string
    {
        $shown = array_slice($parts, 0, 3);
        $more = count($parts) - count($shown);

        if ($more > 0) {
            return implode(', ', $shown).' and '.$more.' more';
        }

        return count($shown) > 1
            ? implode(', ', array_slice($shown, 0, -1)).' and '.end($shown)
            : ($shown[0] ?? '');
    }
}
