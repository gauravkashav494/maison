<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Validation\Rule;

class QuoteRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'problem' => $this->message(min: 10, max: 3000),
            'address' => $this->addressLine(required: false, max: 300),
            'area' => ['nullable', 'string', 'max:120'],
            'name' => $this->personName(max: 80),
            'phone' => $this->phone(),
            'email' => $this->email(required: false),
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['problem.required' => 'Please describe the work you need done.'] + parent::messages();
    }
}
