<?php

namespace App\Filament\Widgets;

use App\Admin\StoreQuota;
use Filament\Widgets\Widget;

/** Product and storage usage of the store the session manages. Hidden when both are unlimited. */
class StoreUsage extends Widget
{
    protected string $view = 'filament.widgets.store-usage';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        $quota = StoreQuota::current();

        return $quota && ($quota->productLimit() !== null || $quota->storageLimit() !== null);
    }

    protected function getViewData(): array
    {
        $quota = StoreQuota::current();

        return [
            'meters' => array_values(array_filter([
                $quota->productLimit() === null ? null : [
                    'label' => 'Products', 'used' => number_format($quota->productCount()), 'total' => number_format($quota->productLimit()),
                    'percent' => $quota->productPercent(), 'note' => $quota->atProductLimit() ? 'Limit reached — delete a product or ask for a higher limit.' : $quota->productsLeft().' left',
                ],
                $quota->storageLimit() === null ? null : [
                    'label' => 'Media storage', 'used' => StoreQuota::formatBytes($quota->storageUsed()), 'total' => StoreQuota::formatBytes($quota->storageLimit()),
                    'percent' => $quota->storagePercent(), 'note' => $quota->storageFull() ? 'Full — delete media or ask for more space.' : StoreQuota::formatBytes($quota->storageLeft()).' left',
                ],
            ])),
        ];
    }
}
