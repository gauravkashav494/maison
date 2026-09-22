<?php

namespace App\Policies\Scoped;

use App\Models\ServiceRequest;
use App\Policies\StoreScopedPolicy;

class ServiceRequestPolicy extends StoreScopedPolicy
{
    protected string $model = ServiceRequest::class;
}
