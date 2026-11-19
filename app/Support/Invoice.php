<?php

namespace App\Support;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tax invoice (bill) for an order, as a PDF.
 *
 * Every figure comes from the order as it was placed (items, discount, delivery, tax rate and
 * label, whether prices included tax), so changing the store's settings later never rewrites
 * an old invoice. Only the seller's details (Site settings → Checkout → Invoice) are read live.
 *
 * The invoice number is the store's own GST series (Order::assignInvoiceNumber, e.g.
 * INV/26-27/0001); the order number is printed beside it so the two can always be matched.
 */
class Invoice
{
    public function __construct(private Order $order) {}

    public static function for(Order $order): self
    {
        $order->assignInvoiceNumber();

        return new self($order->loadMissing('items'));
    }

    public function filename(): string
    {
        return 'Invoice-'.str_replace('/', '-', $this->order->invoice_number).'.pdf';
    }

    public function pdf(): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('invoices.order', $this->data())->setPaper('a4');
    }

    /** Shown in the browser's PDF viewer instead of saved. */
    public function stream(): Response
    {
        return $this->pdf()->stream($this->filename());
    }

    public function download(): Response
    {
        return $this->pdf()->download($this->filename());
    }

    /** Raw PDF bytes, for an admin download or an email attachment. */
    public function output(): string
    {
        return $this->pdf()->output();
    }

    /** Everything the invoice view prints. */
    public function data(): array
    {
        $order = $this->order;
        $tax = (float) $order->tax;
        // One row per rate the order's products carry ("CGST + SGST" splits each in two).
        $taxRows = $order->taxRows();
        // A rate column on the items once any line carries its own rate.
        $itemRates = $order->items->contains(fn ($item) => $item->tax_rate !== null);

        return [
            'order' => $order,
            'seller' => [
                'name' => setting('checkout.invoice_business_name') ?: setting('site.name', config('app.name')),
                'address' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) setting('checkout.invoice_address', ''))))),
                'gstin' => setting('checkout.invoice_gstin'),
                'email' => setting('site.contact_email'),
                'phone' => setting('site.contact_phone'),
                'note' => setting('checkout.invoice_note'),
            ],
            'taxRows' => $taxRows,
            'itemRates' => $itemRates,
            'taxLabel' => $order->tax_label ?: 'GST',
            'inclusive' => (bool) $order->prices_include_tax,
            'taxableValue' => $order->prices_include_tax ? max(0, (float) $order->total - $tax) : null,
            // Where the supply is made, for a GST invoice: the shipping state, e.g. "Punjab (03)".
            // A sales order has no address: it is supplied at the counter, in the seller's state.
            'placeOfSupply' => $order->salesperson_id
                ? GstSupply::placeOfSupply(SalesOrder::supplyState(), 'India')
                : GstSupply::placeOfSupply($order->shipping_state, $order->shipping_country),
            'paymentMethod' => Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method,
            'amountInWords' => self::rupeesInWords((float) $order->total),
        ];
    }

    /** 1234.5 → "Rupees One Thousand Two Hundred Thirty-Four and Fifty Paise Only" (Indian lakh/crore grouping). */
    public static function rupeesInWords(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $words = 'Rupees '.($rupees ? self::words($rupees) : 'Zero');
        if ($paise) {
            $words .= ' and '.self::words($paise).' Paise';
        }

        return $words.' Only';
    }

    private static function words(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
            'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $belowHundred = fn (int $x) => $x < 20 ? $ones[$x] : $tens[intdiv($x, 10)].($x % 10 ? '-'.$ones[$x % 10] : '');
        $belowThousand = fn (int $x) => trim(($x >= 100 ? $ones[intdiv($x, 100)].' Hundred ' : '').($x % 100 ? $belowHundred($x % 100) : ''));

        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$unit, $name]) {
            if ($n >= $unit) {
                $parts[] = ($unit === 10000000 ? self::words(intdiv($n, $unit)) : $belowHundred(intdiv($n, $unit))).' '.$name;
                $n %= $unit;
            }
        }
        if ($n) {
            $parts[] = $belowThousand($n);
        }

        return implode(' ', $parts);
    }
}
