<?php

namespace App\Policies\Scoped;

use App\Models\Menu;
use App\Policies\StoreScopedPolicy;

class MenuPolicy extends StoreScopedPolicy
{
    protected string $model = Menu::class;
}
