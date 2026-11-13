<?php

namespace App\Http\Requests\Storefront;

use App\Models\ServiceRequest;
use Illuminate\Validation\Rule;

class BookingRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'service_name' => ['required_without:service_id', 'nullable', 'string', 'min:3', 'max:120'],
            'problem' => ['nullable', 'string', 'max:2000'],
            'address' => $this->addressLine(max: 300),
            'area' => ['nullable', 'string', 'max:120'],
            'service_area_id' => ['nullable', 'integer', Rule::exists('service_areas', 'id')],
            'preferred_date' => ['required_unless:time_slot,asap', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addMonths(3)->toDateString()],
            'time_slot' => ['required', Rule::in(array_keys(ServiceRequest::SLOTS))],
            'name' => $this->personName(max: 80),
            'phone' => $this->phone(),
            'email' => $this->email(required: false),
            'type' => ['nullable', Rule::in(['booking', 'emergency'])],
            'website' => ['prohibited'], // honeypot
        ];
    }

    public function messages(): array
    {
        return [
            'service_name.required_without' => 'Pick a service or tell us what you need.',
            'address.required' => 'Enter the address where the plumber should come.',
            'preferred_date.required_unless' => 'Choose a day.',
            'preferred_date.after_or_equal' => 'Choose today or a later day.',
            'preferred_date.before_or_equal' => 'Bookings can be made up to 3 months ahead.',
            'preferred_date.date_format' => 'Choose a valid day.',
            'time_slot.required' => 'Choose a time.',
            'time_slot.in' => 'Choose one of the available times.',
            'phone.required' => 'Enter your mobile number.',
        ] + parent::messages();
    }
}
