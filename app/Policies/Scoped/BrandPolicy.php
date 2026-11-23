<?php

namespace App\Policies\Scoped;

use App\Models\Brand;
use App\Policies\StoreScopedPolicy;

class BrandPolicy extends StoreScopedPolicy
{
    protected string $model = Brand::class;
}
