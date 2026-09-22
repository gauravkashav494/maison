<?php

namespace App\Policies\Scoped;

use App\Models\Faq;
use App\Policies\StoreScopedPolicy;

class FaqPolicy extends StoreScopedPolicy
{
    protected string $model = Faq::class;
}
