<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

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

        return $data;
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
        return [ViewAction::make(), DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()->label('Delete permanently')];
    }
}
