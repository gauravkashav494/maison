<?php

namespace App\Policies\Scoped;

use App\Admin\StoreQuota;
use App\Models\Product;
use App\Models\User;
use App\Policies\StoreScopedPolicy;

class ProductPolicy extends StoreScopedPolicy
{
    protected string $model = Product::class;

    /** A store may not add more products than its plan allows. */
    public function create(User $user): bool
    {
        if (! parent::create($user)) {
            return false;
        }

        return ! (StoreQuota::current()?->atProductLimit() ?? false);
    }
}
