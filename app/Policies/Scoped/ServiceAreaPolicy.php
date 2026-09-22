<?php

namespace App\Policies\Scoped;

use App\Models\ServiceArea;
use App\Policies\StoreScopedPolicy;

class ServiceAreaPolicy extends StoreScopedPolicy
{
    protected string $model = ServiceArea::class;
}
