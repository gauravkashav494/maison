<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\ExplainsMissingFields;
use App\Filament\Resources\Products\Pages\Concerns\ValidatesProductImages;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use ExplainsMissingFields;
    use ValidatesProductImages;

    protected static string $resource = ProductResource::class;

    /**
     * Create sits at the foot of Pricing & Variants (and the tabs after it), where a product's
     * price is set; the tabs before it end with Next (ProductForm::tabButtons()). Only Cancel stays here.
     */
    protected function getFormActions(): array
    {
        return [$this->getCancelFormAction()];
    }
}
