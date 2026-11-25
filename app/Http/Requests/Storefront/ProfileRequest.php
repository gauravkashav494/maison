<?php

namespace App\Http\Requests\Storefront;

use App\Support\CustomerPhone;
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
            // Unique inside this client too, in any shape it was saved in.
            'phone' => [...$this->phone(required: false), CustomerPhone::uniqueRule((int) $user->client_id, $user->id, 'Another account already uses this phone number.')],
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'Another account already uses this email address.'] + parent::messages();
    }
}
