<?php

namespace App\Policies\Scoped;

use App\Models\Page;
use App\Policies\StoreScopedPolicy;

class PagePolicy extends StoreScopedPolicy
{
    protected string $model = Page::class;
}
