<?php

namespace App\Http\Requests\Storefront;

class AddressRequest extends StorefrontRequest
{
    protected array $lowercase = [];

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:40'],
            'name' => $this->personName(),
            'phone' => $this->phone(required: false),
            'line1' => $this->addressLine(),
            'line2' => $this->addressLine(required: false),
            'city' => $this->place(),
            'state' => $this->place(),
            'postal_code' => $this->postalCode($this->input('country')),
            'country' => $this->place(),
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
