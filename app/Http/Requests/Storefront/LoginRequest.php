<?php

namespace App\Http\Requests\Storefront;

class LoginRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'email' => $this->email(),
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }
}
