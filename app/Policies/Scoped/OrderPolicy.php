<?php

namespace App\Policies\Scoped;

use App\Models\Order;
use App\Policies\StoreScopedPolicy;

class OrderPolicy extends StoreScopedPolicy
{
    protected string $model = Order::class;
}
