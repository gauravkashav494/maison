<?php

namespace App\Http\Requests\Storefront;

/** The phone-first contact form of service templates. */
class ServicesContactRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'name' => $this->personName(max: 80),
            'phone' => $this->phone(),
            'email' => $this->email(required: false),
            'service' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'message' => $this->message(),
            'website' => ['prohibited'],
        ];
    }
}
