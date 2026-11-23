<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Support\Pages\CreateRecord;

class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;
}
