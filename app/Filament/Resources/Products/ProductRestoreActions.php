<?php

namespace App\Filament\Resources\Products;

use App\Clients\ClientQuota;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;

class ProductRestoreActions
{
    public static function restore(): RestoreAction
    {
        return RestoreAction::make()->before(fn (RestoreAction $action) => self::check($action));
    }

    public static function restoreBulk(): RestoreBulkAction
    {
        return RestoreBulkAction::make()->before(fn (RestoreBulkAction $action) => self::check($action));
    }

    private static function check(RestoreAction|RestoreBulkAction $action): void
    {
        if (! (ClientQuota::current()?->atProductLimit() ?? false)) {
            return;
        }

        Notification::make()
            ->danger()
            ->title('Product limit reached')
            ->body('Increase your product limit or delete another product before restoring this one.')
            ->send();

        $action->halt();
    }
}
