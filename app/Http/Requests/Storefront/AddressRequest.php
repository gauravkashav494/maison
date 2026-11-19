<?php

namespace App\Http\Requests\Storefront;

class AddressRequest extends StorefrontRequest
{
    protected array $lowercase = [];

    protected array $addressFields = ['state' => 'country'];

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:40'],
            'name' => $this->personName(),
            'phone' => $this->phone(required: false),
            'line1' => $this->addressLine(),
            'line2' => $this->addressLine(required: false),
            'city' => $this->place(),
            'state' => $this->indianState(),
            'postal_code' => $this->postalCode(),
            'country' => $this->indiaCountry(),
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
