<?php

namespace App\Support;

use App\Clients\ClientContext;
use App\Models\Customer;
use App\Models\Store;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;

/**
 * Orders placed by a sales person (Customer::isSalesperson()).
 *
 * A sales person signs in on the store like any customer, but checks out without a delivery
 * address or delivery method: the goods are handed over at the store. They place the order
 * either for themselves ("self") or for a customer they are serving ("customer": name and phone,
 * email optional), and the order keeps them as its sales person either way.
 *
 * The store's own address is written into the order's address fields, so the invoice, tracking
 * page and GST (CGST + SGST within the store's state) read it like any other order.
 */
class SalesOrder
{
    public const FOR_SELF = 'self';

    public const FOR_CUSTOMER = 'customer';

    /** The one "delivery method" a sales person's cart has: nothing to deliver, nothing to pay. */
    public const SHIPPING_CODE = 'sales_order';

    /** The signed-in storefront customer, when they are a sales person. */
    public static function salesperson(): ?Customer
    {
        $user = Auth::guard('web')->user();

        return $user instanceof Customer && $user->isSalesperson() ? $user : null;
    }

    public static function active(): bool
    {
        return self::salesperson() !== null;
    }

    /** @return array{code:string,name:string,description:?string,cost:float,free_over:?float,eta:?string} */
    public static function shippingMethod(): array
    {
        return [
            'code' => self::SHIPPING_CODE,
            'name' => 'No delivery (sales order)',
            'description' => 'Handed over at the store',
            'cost' => 0.0,
            'free_over' => null,
            'eta' => null,
        ];
    }

    /**
     * The store's address, for the order's address fields: its first active store location,
     * else the business address and state from Site settings → Invoice.
     *
     * @return array{line1:string,line2:?string,city:string,state:string,postal_code:string,country:string}
     */
    public static function storeAddress(): array
    {
        if ($store = Store::active()->first()) {
            return [
                'line1' => (string) $store->address,
                'line2' => null,
                'city' => (string) $store->city,
                'state' => (string) $store->state,
                'postal_code' => (string) $store->postal_code,
                'country' => (string) ($store->country ?: 'India'),
            ];
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) setting('checkout.invoice_address', '')))));
        $state = GstSupply::sellerStateCode();

        return [
            'line1' => $lines[0] ?? (string) (setting('checkout.invoice_business_name') ?: 'At the store'),
            'line2' => count($lines) > 1 ? implode(', ', array_slice($lines, 1)) : null,
            'city' => '',
            'state' => $state ? IndianStates::STATES[$state] : '',
            'postal_code' => '',
            'country' => 'India',
        ];
    }

    /**
     * The validated checkout form as PlaceOrder takes it, for a sales person's order: the
     * contact is the sales person ("self") or the customer they typed in, and the address is
     * the store's. Nothing is delivered, so nothing is saved as an address.
     *
     * @return array{0: array, 1: Customer} the form data and the customer the order belongs to
     */
    public static function prepare(array $data, Customer $salesperson): array
    {
        $forCustomer = ($data['order_for'] ?? self::FOR_SELF) === self::FOR_CUSTOMER;
        $customer = $forCustomer
            ? self::customerFor($salesperson, $data['shipping_name'], $data['phone'], $data['email'] ?? null)
            : $salesperson;
        $address = self::storeAddress();

        $data = [
            'phone' => $forCustomer ? $data['phone'] : (string) $salesperson->phone,
            'email' => $forCustomer ? ($data['email'] ?? null) : $salesperson->email,
            'shipping_name' => $forCustomer ? $data['shipping_name'] : $salesperson->name,
            'shipping_line1' => $address['line1'],
            'shipping_line2' => $address['line2'],
            'shipping_city' => $address['city'],
            'shipping_state' => $address['state'],
            'shipping_postal_code' => $address['postal_code'],
            'shipping_country' => $address['country'],
            'shipping_method' => self::SHIPPING_CODE,
            'salesperson_id' => $salesperson->id,
        ] + $data;

        return [$data, $customer];
    }

    /**
     * This client's customer for the phone the sales person typed (a registered account first),
     * else the one for the email, else a new guest record. A sales person is never the match:
     * the order is for someone else. An existing customer's details are never changed from here.
     */
    public static function customerFor(Customer $salesperson, string $name, string $phone, ?string $email): Customer
    {
        $clientId = $salesperson->client_id ?? app(ClientContext::class)->id();

        $byPhone = self::existingCustomerByPhone((int) $clientId, $phone);
        if ($byPhone) {
            return $byPhone;
        }

        if (filled($email) && ($byEmail = GuestCustomer::forEmail($email, $name, $phone, $clientId)) && ! $byEmail->isSalesperson()) {
            return $byEmail;
        }

        return Customer::create([
            'client_id' => $clientId,
            'name' => trim($name),
            'email' => null,
            'phone' => $phone,
            'password' => null,
            'is_active' => true,
        ]);
    }

    /** Find this client's non-salesperson customer using national or previously formatted phone data. */
    public static function existingCustomerByPhone(int $clientId, string $phone): ?Customer
    {
        $national = PhoneNumber::national('IN', $phone) ?? $phone;
        $formatted = PhoneNumber::format('IN', $national);
        $phoneForms = array_values(array_unique(array_filter([
            $national,
            $formatted,
            $formatted ? preg_replace('/\s+/', '', $formatted) : null,
            '91'.$national,
            '0'.$national,
            substr($national, 0, 5).' '.substr($national, 5),
        ])));

        return Customer::where('client_id', $clientId)
            ->whereIn('phone', $phoneForms)
            ->where('is_salesperson', false)
            ->get()
            ->sortByDesc(fn (Customer $customer) => $customer->isAccount())
            ->first();
    }
}
