<?php

namespace App\Filament\Resources\Products;

use App\Clients\ClientQuota;
use App\Models\Product;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Restore for products, held to the client's allowances. A product in the bin counts towards
 * neither the product limit nor the image limit, so bringing one back has to fit both.
 */
class ProductRestoreActions
{
    public static function restore(): RestoreAction
    {
        return RestoreAction::make()->before(fn (RestoreAction $action, Product $record) => self::check($action, collect([$record])));
    }

    public static function restoreBulk(): RestoreBulkAction
    {
        return RestoreBulkAction::make()->before(fn (RestoreBulkAction $action, Collection $records) => self::check($action, $records));
    }

    /** @param  Collection<int, Product>  $records */
    private static function check(RestoreAction|RestoreBulkAction $action, Collection $records): void
    {
        $quota = ClientQuota::current();
        $records = $records->filter(fn ($record) => $record instanceof Product && $record->trashed());
        if (! $quota || $records->isEmpty()) {
            return;
        }

        $one = $records->count() === 1;

        $productLimit = $quota->productLimit();
        if ($productLimit !== null && $quota->productCount() + $records->count() > $productLimit) {
            self::refuse($action, 'Product limit reached', $one
                ? 'Increase your product limit or delete another product before restoring this one.'
                : sprintf('Only %s more %s fit your product limit. Restore fewer, delete other products, or ask for a higher limit.',
                    number_format($quota->productsLeft()), $quota->productsLeft() === 1 ? 'product' : 'products'));

            return;
        }

        $images = $records->flatMap(fn (Product $product) => ClientQuota::productImages($product))->unique()->values()->all();
        if ($quota->wouldExceedImages($images)) {
            self::refuse($action, 'Image limit reached', sprintf(
                '%s %s images and the account is using %s of %s. Remove some images or ask the platform admin to raise the limit.',
                $one ? 'This product has' : 'These products have',
                number_format(count($images)),
                number_format($quota->imageCount()),
                number_format($quota->imageLimit()),
            ));
        }
    }

    private static function refuse(RestoreAction|RestoreBulkAction $action, string $title, string $body): void
    {
        Notification::make()->danger()->title($title)->body($body)->send();

        $action->halt();
    }
}
