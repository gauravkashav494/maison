<?php

namespace App\Filament\Support;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;

/** Every table's row actions sit behind one ⋮ button, so rows stay compact and look the same everywhere. */
class RowActions
{
    public static function group(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)
            ->icon('heroicon-m-ellipsis-vertical')
            ->tooltip('Actions')
            ->color('gray');
    }

    /**
     * Delete / Restore / Delete permanently for a soft-deleting model. Each one only shows when it
     * applies (Restore and Delete permanently on archived rows) and when the policy allows it.
     */
    public static function trash(): array
    {
        return [
            DeleteAction::make()->modalDescription('It moves to the trash and can be restored later from the "Deleted items" filter.'),
            RestoreAction::make(),
            ForceDeleteAction::make()->label('Delete permanently')
                ->modalDescription('This removes it for good and cannot be undone.'),
        ];
    }

    public static function trashBulk(): array
    {
        return [
            DeleteBulkAction::make()->modalDescription('They move to the trash and can be restored later from the "Deleted items" filter.'),
            RestoreBulkAction::make(),
            ForceDeleteBulkAction::make()->label('Delete permanently')
                ->modalDescription('This removes them for good and cannot be undone.'),
        ];
    }
}
