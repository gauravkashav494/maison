<?php

namespace App\Filament\Support\Pages;

use App\Filament\Support\RecordToast;
use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;

/** Every Create page extends this, so the toast names the new record: 'Product "Linen Shirt" created'. */
abstract class CreateRecord extends BaseCreateRecord
{
    protected function getCreatedNotificationTitle(): ?string
    {
        return RecordToast::createdTitle($this->getRecord());
    }
}
