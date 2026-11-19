<?php

namespace App\Http\Requests\Storefront;

/** The new password, chosen after the phone number was proven with a WhatsApp code. */
class NewPasswordRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return ['password' => $this->newPassword()];
    }
}
