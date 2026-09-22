<?php

namespace App\Policies\Scoped;

use App\Models\Product;
use App\Policies\StoreScopedPolicy;

class ProductPolicy extends StoreScopedPolicy
{
    protected string $model = Product::class;
}
