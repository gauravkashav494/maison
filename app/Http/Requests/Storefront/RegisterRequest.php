<?php

namespace App\Http\Requests\Storefront;

use App\Clients\ClientContext;
use Illuminate\Validation\Rule;

class RegisterRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'name' => $this->personName(),
            // Unique within this client only: the same address may shop with another client.
            // A guest customer (created at checkout, no password yet) is claimed by email instead.
            'email' => [...$this->email(), Rule::unique('customers', 'email')->where('client_id', app(ClientContext::class)->id())->whereNotNull('password')],
            'phone' => $this->phone(required: false),
            'password' => $this->newPassword(),
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'An account with this email already exists. Try signing in instead.'] + parent::messages();
    }
}
