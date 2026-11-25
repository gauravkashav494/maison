<?php

namespace App\Http\Requests\Storefront;

use App\Models\Order;
use App\Services\Cart;
use App\Support\SalesOrder;
use Illuminate\Validation\Rule;

class CheckoutRequest extends StorefrontRequest
{
    protected array $addressFields = ['shipping_state' => 'shipping_country'];

    public function rules(): array
    {
        $payments = array_keys(Order::checkoutPaymentMethods());
        $shipping = collect(app(Cart::class)->shippingMethods())->pluck('code')->filter()->all();

        // A sales person also says who the order is for (SalesOrder::prepare()); the contact and
        // address are then that person's, checked as for any order.
        $salesOrder = SalesOrder::active() ? [
            'order_for' => ['required', Rule::in([SalesOrder::FOR_SELF, SalesOrder::FOR_CUSTOMER])],
        ] : [];

        return $salesOrder + [
            'phone' => $this->phone(),
            'email' => $this->email(required: false),
            'shipping_name' => $this->personName(),
            'shipping_line1' => $this->addressLine(),
            'shipping_line2' => $this->addressLine(required: false),
            'shipping_city' => $this->place(),
            'shipping_state' => $this->indianState(),
            'shipping_postal_code' => $this->postalCode(),
            'shipping_country' => $this->indiaCountry(),
            'shipping_method' => ['required', 'string', $shipping ? Rule::in($shipping) : 'max:40'],
            'payment_method' => ['required', 'string', Rule::in($payments)],
            'notes' => ['nullable', 'string', 'max:500'],
            'save_address' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_method.required' => 'Please choose a delivery method.',
            'payment_method.required' => 'Please choose a payment method.',
            'payment_method.in' => 'That payment method is not available.',
            'shipping_method.in' => 'That delivery method is not available.',
            'order_for.required' => 'Please choose who this order is for.',
            'order_for.in' => 'Please choose who this order is for.',
        ] + (SalesOrder::active() && $this->input('order_for') === SalesOrder::FOR_CUSTOMER ? [
            'phone.required' => 'Please enter the customer\'s phone number.',
            'shipping_name.required' => 'Please enter the customer\'s name.',
        ] : []) + parent::messages();
    }
}
