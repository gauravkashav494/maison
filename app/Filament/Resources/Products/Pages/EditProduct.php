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

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()->label('Delete permanently')];
    }
}
