<?php

namespace App\Filament\Resources\Products\Pages\Concerns;

use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Product;
use Filament\Resources\Pages\EditRecord;

/**
 * Checks the product's images against the account allowance and the per-product cap in the
 * same pass as the rest of the form, with the error on the Media tab's gallery. Done on the
 * page rather than the field: Filament applies an upload field's rules to each file separately,
 * so a field rule never sees the gallery and the colour uploads together, and does not run at
 * all when the gallery is empty.
 */
trait ValidatesProductImages
{
    public function validate($rules = null, $messages = [], $attributes = []): array
    {
        // Only the product form itself (it validates the gallery), not an action's modal.
        if (is_array($rules) && array_key_exists('data.images', $rules)) {
            $record = $this instanceof EditRecord ? $this->getRecord() : null;

            $rules['data.images'] = [
                ...(array) $rules['data.images'],
                ...ProductForm::imageRules($record instanceof Product ? $record : null, (array) $this->data),
            ];
        }

        return parent::validate($rules, $messages, $attributes);
    }
}
