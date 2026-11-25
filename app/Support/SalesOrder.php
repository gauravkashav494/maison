<?php

namespace App\Support;

use App\Clients\ClientContext;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

/**
 * Orders placed by a sales person (Customer::isSalesperson()).
 *
 * A sales person signs in on the store like any customer and checks out like one: contact,
 * delivery address, delivery method and payment, with GST by the delivery state. They also say
 * whether the order is for themselves ("self") or for a customer they are serving ("customer"):
 * the contact and address are then that customer's, and the order keeps the sales person either way.
 *
 * Orders placed before sales orders were delivered have no address: they were handed over at the
 * store, with GST in the seller's own state (supplyState()).
 */
class SalesOrder
{
    public const FOR_SELF = 'self';

    public const FOR_CUSTOMER = 'customer';

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

    /**
     * The seller's own state: the place of supply of an older sales order handed over at the
     * counter (CGST + SGST). Null when no GST state is set.
     */
    public static function supplyState(): ?string
    {
        $code = GstSupply::sellerStateCode();

        return $code ? IndianStates::STATES[$code] : null;
    }

    /**
     * The validated checkout form as PlaceOrder takes it, for a sales person's order: the contact
     * and address are as typed (the sales person's own, or the customer's), the order belongs to
     * the sales person or to that customer, and it keeps the sales person.
     *
     * @return array{0: array, 1: Customer} the form data and the customer the order belongs to
     */
    public static function prepare(array $data, Customer $salesperson): array
    {
        $forCustomer = ($data['order_for'] ?? self::FOR_SELF) === self::FOR_CUSTOMER;
        $customer = $forCustomer
            ? self::customerFor($salesperson, $data['shipping_name'], $data['phone'], $data['email'] ?? null)
            : $salesperson;

        return [['salesperson_id' => $salesperson->id] + $data, $customer];
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
            // Only a sales person can still have this number here (they are never the match):
            // one customer per phone, so it stays on the order only.
            'phone' => CustomerPhone::taken((int) $clientId, $phone) ? null : $phone,
            'password' => null,
            'is_active' => true,
        ]);
    }

    /** Find this client's non-salesperson customer using national or previously formatted phone data. */
    public static function existingCustomerByPhone(int $clientId, string $phone): ?Customer
    {
        return CustomerPhone::query($clientId, $phone)
            ->where('is_salesperson', false)
            ->get()
            ->sortByDesc(fn (Customer $customer) => $customer->isAccount())
            ->first();
    }
}
