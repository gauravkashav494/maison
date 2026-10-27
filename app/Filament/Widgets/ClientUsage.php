<?php

namespace App\Filament\Widgets;

use App\Clients\ClientQuota;
use Filament\Widgets\Widget;

/**
 * Product, image and storage usage of the client the session works in. This is the front-end
 * half of the allowances; the server-side half is ProductPolicy, WithinClientImageLimit and
 * WithinClientStorage. Hidden when everything is unlimited.
 */
class ClientUsage extends Widget
{
    protected string $view = 'filament.widgets.store-usage';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        $quota = ClientQuota::current();

        return $quota && ($quota->productLimit() !== null || $quota->imageLimit() !== null || $quota->storageLimit() !== null);
    }

    protected function getViewData(): array
    {
        $quota = ClientQuota::current();

        return [
            'meters' => array_values(array_filter([
                $quota->productLimit() === null ? null : [
                    'label' => 'Products', 'used' => number_format($quota->productCount()), 'total' => number_format($quota->productLimit()),
                    'percent' => $quota->productPercent(), 'note' => $quota->atProductLimit() ? 'Limit reached — delete a product or ask for a higher limit.' : $quota->productsLeft().' left',
                ],
                $quota->imageLimit() === null ? null : [
                    'label' => 'Product images', 'used' => number_format($quota->imageCount()), 'total' => number_format($quota->imageLimit()),
                    'percent' => $quota->imagePercent(), 'note' => $quota->atImageLimit() ? 'Limit reached — remove an image or ask for a higher limit.' : $quota->imagesLeft().' left',
                ],
                $quota->storageLimit() === null ? null : [
                    'label' => 'Media storage', 'used' => ClientQuota::formatBytes($quota->storageUsed()), 'total' => ClientQuota::formatBytes($quota->storageLimit()),
                    'percent' => $quota->storagePercent(), 'note' => $quota->storageFull() ? 'Full — delete media or ask for more space.' : ClientQuota::formatBytes($quota->storageLeft()).' left',
                ],
            ])),
        ];
    }
}
