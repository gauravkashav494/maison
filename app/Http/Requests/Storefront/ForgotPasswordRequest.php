<?php

namespace App\Http\Requests\Storefront;

/** Forgot password: the phone number on the account (normalised to 10 digits). A WhatsApp code goes there. */
class ForgotPasswordRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return ['phone' => $this->phone(mobileOnly: false)];
    }
}
