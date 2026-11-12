<?php

namespace App\Http\Requests\Storefront;

class ResetPasswordRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => $this->email(),
            'password' => $this->newPassword(),
        ];
    }

    public function messages(): array
    {
        return ['token.required' => 'This reset link is invalid. Please request a new one.'] + parent::messages();
    }
}
