<?php

namespace App\Http\Requests\Storefront;

class BookingLookupRequest extends StorefrontRequest
{
    protected array $lowercase = [];

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'phone' => $this->phone(),
        ];
    }

    public function messages(): array
    {
        return ['reference.regex' => 'Booking references contain only letters, digits and hyphens.'] + parent::messages();
    }
}
