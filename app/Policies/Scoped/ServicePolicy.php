<?php

namespace App\Policies\Scoped;

use App\Models\Service;
use App\Policies\StoreScopedPolicy;

class ServicePolicy extends StoreScopedPolicy
{
    protected string $model = Service::class;
}
