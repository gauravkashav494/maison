<?php

namespace App\Policies\Scoped;

use App\Models\Project;
use App\Policies\StoreScopedPolicy;

class ProjectPolicy extends StoreScopedPolicy
{
    protected string $model = Project::class;
}
