<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Validation\Rule;

class ProfileRequest extends StorefrontRequest
{
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => $this->personName(),
            // Unique inside this client only.
            'email' => [...$this->email(), Rule::unique('customers', 'email')->where('client_id', $user->client_id)->ignore($user->id)],
            'phone' => $this->phone(required: false),
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'Another account already uses this email address.'] + parent::messages();
    }
}
