<?php

namespace App\Policies\Scoped;

use App\Models\Post;
use App\Policies\StoreScopedPolicy;

class PostPolicy extends StoreScopedPolicy
{
    protected string $model = Post::class;
}
