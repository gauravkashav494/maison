<?php

namespace App\Support;

/**
 * IGST or CGST + SGST for an order: GST within the seller's own state is split between the
 * centre and the state (CGST + SGST); to another state or abroad it is IGST. Decided from the
 * seller's state (Site settings → Invoice, else its GSTIN) and where the order is shipped.
 */
class GstSupply
{
    /** The seller's GST state code: the one chosen in the invoice settings, else its GSTIN's. */
    public static function sellerStateCode(): ?string
    {
        $chosen = (string) setting('checkout.invoice_state', '');

        return isset(IndianStates::STATES[$chosen]) ? $chosen : IndianStates::codeFromGstin(setting('checkout.invoice_gstin'));
    }

    /**
     * The tax name to record on an order shipped to $state, $country: "CGST + SGST" within the
     * seller's state, "IGST" to another state or outside India, plain "GST" when either state
     * is unknown.
     */
    public static function labelFor(?string $state, ?string $country): string
    {
        $seller = self::sellerStateCode();
        if ($seller === null) {
            return 'GST';
        }
        if (! IndianStates::isIndia($country)) {
            return blank($country) ? 'GST' : 'IGST';
        }
        $buyer = IndianStates::code($state);
        if ($buyer === null) {
            return 'GST';
        }

        return $buyer === $seller ? 'CGST + SGST' : 'IGST';
    }

    /**
     * What the checkout page needs to name the tax as the customer types their address, as
     * labelFor() does when the order is placed. No seller state: the page keeps "GST".
     *
     * @return array{seller: ?string, states: array<string, string>, india: list<string>}
     */
    public static function checkoutConfig(): array
    {
        $seller = self::sellerStateCode();

        return ['seller' => $seller, 'states' => $seller ? IndianStates::lookup() : [], 'india' => IndianStates::INDIA];
    }

    /** "Punjab (03)", "Outside India", or null when the state is not one we recognise. */
    public static function placeOfSupply(?string $state, ?string $country): ?string
    {
        if (! IndianStates::isIndia($country)) {
            return blank($country) ? null : 'Outside India';
        }
        $code = IndianStates::code($state);

        return $code ? IndianStates::label($code) : null;
    }
}
