<?php

namespace App\Http\Requests\Storefront;

class ReviewRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'name' => $this->personName(max: 80),
            'email' => $this->email(required: false),
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => $this->message(min: 10, max: 2000),
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Please choose a rating.',
            'rating.between' => 'Please choose a rating from 1 to 5 stars.',
            'body.required' => 'Please write your review.',
        ] + parent::messages();
    }
}
