<?php

namespace App\Http\Requests\Storefront;

/** Sign in with a WhatsApp code: just the phone number (normalised to 10 digits). */
class OtpLoginRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return ['phone' => $this->phone()];
    }
}
