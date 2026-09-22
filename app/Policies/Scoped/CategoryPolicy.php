<?php

namespace App\Policies\Scoped;

use App\Models\Category;
use App\Policies\StoreScopedPolicy;

class CategoryPolicy extends StoreScopedPolicy
{
    protected string $model = Category::class;
}
