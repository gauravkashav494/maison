<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Support\RowActions;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Delete and Restore for categories, which keep the tree whole: a category with sub-categories
 * can't go to the trash, and a sub-category can't come back while its parent is in the trash.
 * The same rules are enforced by Category itself; here they are refused with a readable message.
 */
class CategoryDeleteActions
{
    public static function delete(): DeleteAction
    {
        return DeleteAction::make()->before(fn (DeleteAction $action, Category $record) => self::check($action, collect([$record]), 'whyNotDeletable', "Can't delete this category"));
    }

    public static function restore(): RestoreAction
    {
        return RestoreAction::make()->before(fn (RestoreAction $action, Category $record) => self::check($action, collect([$record]), 'whyNotRestorable', "Can't restore this category"));
    }

    /** Row actions: Delete / Restore / Delete permanently. */
    public static function trash(): array
    {
        [$delete, $restore, $forceDelete] = RowActions::trash(self::restore());

        return [
            $delete->before(fn (DeleteAction $action, Category $record) => self::check($action, collect([$record]), 'whyNotDeletable', "Can't delete this category")),
            $restore,
            $forceDelete,
        ];
    }

    public static function trashBulk(): array
    {
        $restore = RestoreBulkAction::make()->before(fn (RestoreBulkAction $action, Collection $records) => self::check($action, $records, 'whyNotRestorable', "Can't restore these categories"));
        [$delete, $restore, $forceDelete] = RowActions::trashBulk($restore);

        return [
            $delete->before(fn (DeleteBulkAction $action, Collection $records) => self::check($action, $records, 'whyNotDeletable', "Can't delete these categories")),
            $restore,
            $forceDelete,
        ];
    }

    /**
     * Stops the whole action, naming every category that blocks it. A bulk action is refused as a
     * whole rather than done in part, so nothing changes until the selection is fixed.
     *
     * @param  Collection<int, Category>  $records
     * @param  'whyNotDeletable'|'whyNotRestorable'  $reason
     */
    private static function check(Action $action, Collection $records, string $reason, string $title): void
    {
        $reasons = $records
            ->filter(fn ($record) => $record instanceof Category && ($reason === 'whyNotDeletable' ? ! $record->trashed() : $record->trashed()))
            ->map(fn (Category $record) => $record->{$reason}())
            ->filter()
            ->values();

        if ($reasons->isEmpty()) {
            return;
        }

        Notification::make()->danger()->title($title)
            ->body($reasons->take(3)->join("\n").($reasons->count() > 3 ? "\n…and ".($reasons->count() - 3).' more.' : ''))
            ->persistent()
            ->send();

        $action->halt();
    }
}
