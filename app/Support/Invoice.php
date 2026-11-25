<?php

namespace App\Support;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tax invoice (bill) for an order, as a PDF.
 *
 * Everything comes from the order as it was placed (items, discount, delivery, tax rate and
 * label, whether prices included tax, and the seller's details from Site settings → Checkout →
 * Invoice), so changing the store's settings later never rewrites an old invoice. Orders placed
 * before the seller's details were copied onto them read those details live.
 *
 * The invoice is issued when the order is dispatched, sales orders included (Order::hasInvoice);
 * until then the customer has an order, not a bill. Its number is
 * the store's own GST series (Order::assignInvoiceNumber, e.g. INV/26-27/0001) and its date the
 * day it was issued; the order number and date are printed beside them.
 */
class Invoice
{
    public function __construct(private Order $order) {}

    /** The order's invoice. Only for an order that has one (Order::hasInvoice): once it is dispatched. */
    public static function for(Order $order): self
    {
        if (! $order->hasInvoice()) {
            throw new \LogicException("Order {$order->number} has no invoice until it is dispatched.");
        }
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
        // An HSN column once any line carries a code; older invoices print as they did.
        $itemHsn = $order->items->contains(fn ($item) => filled($item->hsn_code));

        // As copied onto the order when it was placed; orders from before that read today's settings.
        $seller = $order->seller ?: self::sellerDetails();

        return [
            'order' => $order,
            'seller' => $seller,
            'logo' => self::logoDataUri($seller['logo'] ?? null),
            'taxRows' => $taxRows,
            'itemRates' => $itemRates,
            'itemHsn' => $itemHsn,
            'taxLabel' => $order->tax_label ?: 'GST',
            'inclusive' => (bool) $order->prices_include_tax,
            'taxableValue' => $order->prices_include_tax ? max(0, (float) $order->total - $tax) : null,
            // Where the supply is made, for a GST invoice: the shipping state, e.g. "Punjab (03)".
            // An older sales order has no address: it was supplied at the counter, in the seller's state.
            'placeOfSupply' => $order->salesperson_id && blank($order->shipping_state)
                ? GstSupply::placeOfSupply(array_key_exists('state', $seller) ? $seller['state'] : SalesOrder::supplyState(), 'India')
                : GstSupply::placeOfSupply($order->shipping_state, $order->shipping_country),
            'paymentMethod' => Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method,
            'amountInWords' => self::rupeesInWords((float) $order->total),
        ];
    }

    /**
     * The seller's details as the invoice prints them, from today's settings (Site settings →
     * Checkout → Invoice, and the site's contact details). PlaceOrder copies these onto each
     * order, so an invoice keeps the details that applied on its date.
     *
     * @return array{name: string, brand: ?string, logo: ?string, address: list<string>, gstin: ?string, state: ?string, email: ?string, phone: ?string, note: ?string}
     */
    public static function sellerDetails(): array
    {
        return [
            'name' => setting('checkout.invoice_business_name') ?: setting('site.name', config('app.name')),
            // The store name shoppers know, printed above the registered name when the two differ.
            'brand' => setting('site.name') ?: null,
            // The uploaded logo (Site settings → Brand), printed above the names; a stored path.
            'logo' => setting('site.logo') ?: null,
            'address' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) setting('checkout.invoice_address', ''))))),
            'gstin' => setting('checkout.invoice_gstin') ?: null,
            // The seller's state: the place of supply of an older sales order handed over at the counter.
            'state' => SalesOrder::supplyState(),
            'email' => setting('site.contact_email') ?: null,
            'phone' => setting('site.contact_phone') ?: null,
            'note' => setting('checkout.invoice_note') ?: null,
        ];
    }

    /**
     * The logo as a data URI, so the PDF needs no file access or web request. Null when there
     * is none, it is gone, or it is not an image the PDF can draw.
     */
    public static function logoDataUri(?string $path): ?string
    {
        if (blank($path) || str_contains($path, '://')) {
            return null;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }
        $mime = $disk->mimeType($path);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }

    /** 1234.5 →"Rupees One Thousand Two Hundred Thirty-Four and Fifty Paise Only" (Indian lakh/crore grouping). */
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
