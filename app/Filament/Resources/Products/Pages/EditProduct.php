<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\ExplainsMissingFields;
use App\Filament\Resources\Products\Pages\Concerns\ValidatesProductImages;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\ProductRestoreActions;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Support\Pages\EditRecord;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;

class EditProduct extends EditRecord
{
    use ExplainsMissingFields;
    use ValidatesProductImages;

    protected static string $resource = ProductResource::class;

    /** The stock each field showed when the form was filled (stockOf()), to see what moved since. */
    public array $stockWhenOpened = ['stock' => 0, 'variants' => []];

    /**
     * An older product keeps its sizes and colours in two plain lists. They open as Size and
     * Colour options, so there is one place to edit them; saving writes the lists back.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (empty($data['variant_attributes']) && (! empty($data['colors']) || ! empty($data['sizes']))) {
            $data['variant_attributes'] = array_map(
                fn (array $attribute) => ['name' => $attribute['name'], 'values' => $attribute['values'], 'swatches' => $attribute['swatches']],
                $this->getRecord()->variantAttributes(),
            );
        }

        // A combination saved without "is_active" is available on the storefront
        // (Product::variantList). The form's Available switch would read it as off, and simply
        // saving the product would then make it unbuyable, so it opens as available.
        if (is_array($data['variants'] ?? null)) {
            $data['variants'] = array_map(fn ($row) => is_array($row) ? $row + ['is_active' => true] : $row, $data['variants']);
        }

        $this->stockWhenOpened = $this->stockOf((bool) ($data['has_variants'] ?? false), (array) ($data['variants'] ?? []), (int) ($data['stock'] ?? 0));

        return $data;
    }

    /**
     * Stock moves while the form is open: an order, a delivery received, a stock count. Saving
     * must not put back the number the form opened with, so:
     *  - a product that has been ordered has its stock locked on this form (ProductForm::stockLocked();
     *    it changes in Inventory → Stock levels): its stock, and each existing variant's, is
     *    saved as it is now, whatever the form sent;
     *  - otherwise a stock field left as it was keeps the stock as it is now, and one changed
     *    here, when the stock also moved meanwhile, is not saved: the field shows the stock now
     *    and the person checks it and saves again.
     * A variant added on this form keeps the stock typed for it.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $product = Product::withoutGlobalScopes()->withTrashed()->find($this->getRecord()->getKey());
        if (! $product) {
            return $data;
        }

        $locked = ProductForm::stockLocked($product);
        $now = $this->stockOf((bool) $product->has_variants, (array) $product->variants, (int) $product->stock);
        $opened = $this->stockWhenOpened;
        $moved = [];

        if ((bool) ($data['has_variants'] ?? false)) {
            // The rows as the form holds them, to show the stock now in the right one.
            $rows = [];
            foreach ((array) ($this->data['variants'] ?? []) as $uuid => $row) {
                if (is_array($row) && ($key = $this->variantKey((array) ($row['options'] ?? []))) !== null) {
                    $rows[$key] = $uuid;
                }
            }

            foreach ((array) ($data['variants'] ?? []) as $i => $row) {
                $key = is_array($row) ? $this->variantKey((array) ($row['options'] ?? [])) : null;
                if ($key === null || ! isset($now['variants'][$key])) {
                    continue; // added on this form: as typed
                }
                if ($locked) {
                    $data['variants'][$i]['stock'] = $now['variants'][$key];

                    continue;
                }
                if (! isset($opened['variants'][$key]) || $now['variants'][$key] === $opened['variants'][$key]) {
                    continue;
                }
                if ((int) ($row['stock'] ?? 0) === $opened['variants'][$key]) {
                    $data['variants'][$i]['stock'] = $now['variants'][$key];
                } else {
                    $moved[] = implode(' / ', array_values(json_decode($key, true))).': '.$opened['variants'][$key].' → '.$now['variants'][$key];
                    if (isset($rows[$key])) {
                        $this->data['variants'][$rows[$key]]['stock'] = $now['variants'][$key];
                    }
                }
            }
            // The product's stock is its variants' total, as the form saves it.
            $data['stock'] = (int) collect((array) $data['variants'])
                ->filter(fn ($v) => is_array($v) && ($v['is_active'] ?? true))
                ->sum(fn (array $v) => max(0, (int) ($v['stock'] ?? 0)));
        } elseif ($locked) {
            $data['stock'] = $now['stock'];
        } elseif (! $product->has_variants && $now['stock'] !== $opened['stock']) {
            if ((int) ($data['stock'] ?? 0) === $opened['stock']) {
                $data['stock'] = $now['stock'];
            } else {
                $moved[] = $opened['stock'].' → '.$now['stock'];
                $this->data['stock'] = $now['stock'];
            }
        }

        if ($moved) {
            // What the form shows now is the stock as it is: a save after checking it goes through.
            $this->stockWhenOpened = $now;
            Notification::make()->warning()->persistent()
                ->title('Stock changed while you were editing')
                ->body('A delivery or stock adjustment changed it ('.implode('; ', $moved).'). '
                    .'The stock field now shows the current number: check it, change it again if needed, and save.')
                ->send();
            $this->halt();
        }

        return $data;
    }

    /** @return array{stock: int, variants: array<string, int>} the stock, and each variant's by its options */
    private function stockOf(bool $hasVariants, array $variants, int $stock): array
    {
        $byVariant = [];
        foreach ($hasVariants ? $variants : [] as $row) {
            if (is_array($row) && ($key = $this->variantKey((array) ($row['options'] ?? []))) !== null) {
                $value = $row['stock'] ?? null;
                $byVariant[$key] = $value === null || $value === '' ? $stock : (int) $value;
            }
        }

        return ['stock' => $stock, 'variants' => $byVariant];
    }

    /** A variant's options as one key, whatever their order or spacing. */
    private function variantKey(array $options): ?string
    {
        $options = array_filter(array_map(fn ($v) => trim((string) $v), $options), 'strlen');
        ksort($options);

        return $options ? json_encode($options) : null;
    }

    /**
     * Saving can add to what was typed (photos uploaded under a colour join the images; a photo
     * named after a colour is picked for it): show the product as saved, so the next save keeps it.
     */
    protected function afterSave(): void
    {
        $this->getRecord()->refresh();
        $this->fillForm();
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make(), ProductRestoreActions::restore(), ForceDeleteAction::make()->label('Delete permanently')];
    }
}
