<?php

namespace App\Policies\Scoped;

use App\Models\Subscriber;
use App\Policies\StoreScopedPolicy;

class SubscriberPolicy extends StoreScopedPolicy
{
    protected string $model = Subscriber::class;
}
