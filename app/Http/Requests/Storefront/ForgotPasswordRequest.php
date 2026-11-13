<?php

namespace App\Http\Requests\Storefront;

class ForgotPasswordRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return ['email' => $this->email()];
    }
}
