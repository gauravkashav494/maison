<?php

namespace App\Http\Requests\Storefront;

class PasswordUpdateRequest extends StorefrontRequest
{
    protected array $lowercase = [];

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [...$this->newPassword(), 'different:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'Your new password must be different from the current one.',
        ] + parent::messages();
    }
}
