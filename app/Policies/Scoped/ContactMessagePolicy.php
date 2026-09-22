<?php

namespace App\Policies\Scoped;

use App\Models\ContactMessage;
use App\Policies\StoreScopedPolicy;

class ContactMessagePolicy extends StoreScopedPolicy
{
    protected string $model = ContactMessage::class;
}
