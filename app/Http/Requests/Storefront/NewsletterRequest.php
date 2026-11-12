<?php

namespace App\Http\Requests\Storefront;

class NewsletterRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'email' => $this->email(),
            'source' => ['nullable', 'string', 'max:60'],
        ];
    }
}
