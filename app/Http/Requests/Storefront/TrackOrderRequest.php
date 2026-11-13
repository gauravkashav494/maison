<?php

namespace App\Http\Requests\Storefront;

class TrackOrderRequest extends StorefrontRequest
{
    /** A GET form: always come back to the empty tracking page rather than the previous URL. */
    protected $redirectRoute = 'track';

    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]+$/'],
            'email' => $this->email(),
        ];
    }

    public function messages(): array
    {
        return ['number.regex' => 'Order numbers contain only letters, digits and hyphens, e.g. ME-260916-AB12.'] + parent::messages();
    }
}
