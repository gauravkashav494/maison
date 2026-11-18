<?php

namespace App\Http\Requests\Storefront;

/** Customers sign in with their phone number (normalised to 10 digits) and password. */
class LoginRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'phone' => $this->phone(),
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }
}
