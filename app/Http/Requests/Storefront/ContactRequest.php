<?php

namespace App\Http\Requests\Storefront;

class ContactRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'name' => $this->personName(max: 80),
            'email' => $this->email(),
            'phone' => $this->phone(required: false),
            'subject' => ['nullable', 'string', 'max:120'],
            'message' => $this->message(),
            'website' => ['prohibited'], // honeypot
        ];
    }
}
