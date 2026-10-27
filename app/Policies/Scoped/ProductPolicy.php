<?php

namespace App\Policies\Scoped;

use App\Clients\ClientQuota;
use App\Contracts\AdminIdentity;
use App\Models\Product;
use App\Policies\StoreScopedPolicy;

class ProductPolicy extends StoreScopedPolicy
{
    protected string $model = Product::class;

    /** A client may not add more products than its plan allows. */
    public function create(AdminIdentity $user): bool
    {
        if (! parent::create($user)) {
            return false;
        }

        return ! (ClientQuota::current()?->atProductLimit() ?? false);
    }
}
