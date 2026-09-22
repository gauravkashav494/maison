<?php

namespace App\Policies\Scoped;

use App\Models\Coupon;
use App\Policies\StoreScopedPolicy;

class CouponPolicy extends StoreScopedPolicy
{
    protected string $model = Coupon::class;
}
