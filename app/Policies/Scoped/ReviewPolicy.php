<?php

namespace App\Policies\Scoped;

use App\Models\Review;
use App\Policies\StoreScopedPolicy;

class ReviewPolicy extends StoreScopedPolicy
{
    protected string $model = Review::class;
}
