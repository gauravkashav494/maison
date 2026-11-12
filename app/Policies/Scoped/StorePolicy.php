<?php

namespace App\Policies\Scoped;

use App\Models\Store;
use App\Policies\StoreScopedPolicy;

class StorePolicy extends StoreScopedPolicy
{
    protected string $model = Store::class;
}
