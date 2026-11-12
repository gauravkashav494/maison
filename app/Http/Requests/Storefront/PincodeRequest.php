<?php

namespace App\Http\Requests\Storefront;

class PincodeRequest extends StorefrontRequest
{
    protected array $lowercase = [];

    public function rules(): array
    {
        return ['pincode' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/']];
    }

    public function messages(): array
    {
        return ['pincode.required' => 'Please enter your PIN code.', 'pincode.regex' => 'Please enter a valid 6-digit PIN code.'] + parent::messages();
    }
}
