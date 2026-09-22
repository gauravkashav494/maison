<?php

namespace App\Policies\Scoped;

use App\Models\Testimonial;
use App\Policies\StoreScopedPolicy;

class TestimonialPolicy extends StoreScopedPolicy
{
    protected string $model = Testimonial::class;
}
