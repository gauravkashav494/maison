<?php

namespace App\Http\Requests\Storefront;

use App\Clients\ClientContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class RegisterRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'name' => $this->personName(),
            // The phone number is what the customer signs in with, so it is unique among this client's accounts.
            'phone' => [...$this->phone(), $this->uniqueAccount('phone')],
            // Optional (the phone is the sign-in). Unique within this client only: the same address may
            // shop with another client. A guest customer (created at checkout, no password yet) is claimed by email instead.
            'email' => [...$this->email(required: false), $this->uniqueAccount('email')],
            'password' => $this->newPassword(),
        ];
    }

    /** Unique among this client's accounts (a password or a verified phone); guest records do not count. */
    private function uniqueAccount(string $column): Unique
    {
        return Rule::unique('customers', $column)
            ->where('client_id', app(ClientContext::class)->id())
            ->where(fn ($q) => $q->whereNotNull('password')->orWhereNotNull('phone_verified_at'));
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists. Try signing in instead.',
            'phone.unique' => 'An account with this phone number already exists. Try signing in instead.',
        ] + parent::messages();
    }
}
