<?php

namespace App\Policies\Scoped;

use App\Models\Tax;
use App\Policies\StoreScopedPolicy;

class TaxPolicy extends StoreScopedPolicy
{
    protected string $model = Tax::class;
}
