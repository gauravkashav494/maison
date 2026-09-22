<?php

namespace App\Policies\Scoped;

use App\Models\Collection;
use App\Policies\StoreScopedPolicy;

class CollectionPolicy extends StoreScopedPolicy
{
    protected string $model = Collection::class;
}
