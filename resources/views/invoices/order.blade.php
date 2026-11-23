{{--
    Tax invoice for one order, rendered to PDF by dompdf (App\Support\Invoice).
    dompdf understands tables and simple CSS only: no flex or grid. DejaVu Sans ships with
    dompdf and has the ₹ sign.
--}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice {{ $order->invoice_number }}</title>
<style>
    @page { margin: 28px 32px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2328; line-height: 1.45; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .muted { color: #6a737d; }
    .right { text-align: right; }
    .center { text-align: center; }
    .nowrap { white-space: nowrap; }
    h1 { font-size: 20px; margin: 0; letter-spacing: 1px; }
    .seller-name { font-size: 15px; font-weight: bold; margin: 0 0 2px; }
    .head-cell { padding-bottom: 14px; border-bottom: 2px solid #1f2328; }
    .meta td { padding: 1px 0; }
    .meta .k { color: #6a737d; padding-right: 10px; }
    .parties { margin-top: 14px; }
    .parties td { width: 50%; padding-right: 16px; }
    .label { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #6a737d; margin-bottom: 3px; }
    .items { margin-top: 16px; }
    .items th { background: #f3f4f6; font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; padding: 6px; border-bottom: 1px solid #d0d7de; text-align: left; }
    .items th.right { text-align: right; }
    .items td { padding: 6px; border-bottom: 1px solid #eaeef2; }
    .items .opt { color: #6a737d; font-size: 8.5px; }
    .totals { margin-top: 10px; }
    .totals td { padding: 3px 6px; }
    .totals .grand td { border-top: 1.5px solid #1f2328; font-size: 12px; font-weight: bold; padding-top: 6px; }
    .words { margin-top: 10px; padding: 7px 8px; background: #f6f8fa; border: 1px solid #eaeef2; }
    .foot { margin-top: 22px; border-top: 1px solid #d0d7de; padding-top: 8px; }
    .cancelled-title { color: #b42318; font-size: 15px; font-weight: bold; letter-spacing: 2px; }
    .cancel-meta { margin-top: 4px; color: #b42318; font-size: 9px; text-align: right; }
    .cancel-note { margin: 0 0 6px; padding: 7px 8px; border: 1px solid #f1b8b2; background: #fef3f2; color: #b42318; }
    .refund td { color: #b42318; font-weight: bold; }
    /* Repeats on every page; dompdf draws position: fixed elements once per page. */
    .watermark { position: fixed; top: 42%; left: 0; right: 0; text-align: center; font-size: 96px; font-weight: bold; letter-spacing: 8px;
                 color: #b42318; opacity: .08; transform: rotate(-30deg); }
</style>
</head>
<body>
@php
    $cancelledOn = $order->isCancelled() ? ($order->cancelled_at ?? $order->updated_at) : null;
@endphp
@if($cancelledOn)<div class="watermark">CANCELLED</div>@endif

<table class="head">
    <tr>
        <td class="head-cell" style="width: 58%">
            <p class="seller-name">{{ $seller['name'] }}</p>
            @foreach($seller['address'] as $line)<div>{{ $line }}</div>@endforeach
            @if($seller['phone'] || $seller['email'])<div class="muted">{{ collect([$seller['phone'], $seller['email']])->filter()->implode(' · ') }}</div>@endif
            @if($seller['gstin'])<div><strong>GSTIN:</strong> {{ $seller['gstin'] }}</div>@endif
        </td>
        <td class="head-cell right">
            <h1>{{ count($taxRows) ? 'TAX INVOICE' : 'INVOICE' }}</h1>
            @if($cancelledOn)<div class="cancelled-title">CANCELLED</div>@endif
            <table class="meta" style="width: auto; margin-left: auto; margin-top: 6px;">
                <tr><td class="k">Invoice no.</td><td class="right"><strong>{{ $order->invoice_number }}</strong></td></tr>
                <tr><td class="k">Invoice date</td><td class="right">{{ ($order->invoiced_at ?? $order->created_at)->format('d M Y') }}</td></tr>
                <tr><td class="k">Order no.</td><td class="right">{{ $order->number }}</td></tr>
                <tr><td class="k">Order date</td><td class="right">{{ $order->created_at->format('d M Y') }}</td></tr>
                @if($placeOfSupply ?? null)<tr><td class="k">Place of supply</td><td class="right">{{ $placeOfSupply }}</td></tr>@endif
                <tr><td class="k">Payment</td><td class="right">{{ $paymentMethod }} · {{ $order->paymentStatusLabel() }}</td></tr>
            </table>
            @if($cancelledOn)
                @php
                    // Customer cancellations are stored as "Cancelled by customer: <reason>".
                    $byCustomer = str_starts_with((string) $order->cancellation_reason, 'Cancelled by customer');
                    $reason = $byCustomer ? ltrim(substr((string) $order->cancellation_reason, strlen('Cancelled by customer')), ': ') : (string) $order->cancellation_reason;
                @endphp
                <div class="cancel-meta">
                    Cancelled on {{ $cancelledOn->format('d M Y') }}{{ $byCustomer ? ' by the customer' : '' }}
                    @if($reason !== '')<br>Reason: {{ $reason }}@endif
                </div>
            @endif
        </td>
    </tr>
</table>

<table class="parties">
    <tr>
        <td>
            <div class="label">Bill to</div>
            <strong>{{ $order->shipping_name }}</strong>
            @if($order->phone)<div>{{ $order->phone }}</div>@endif
            @if($order->email)<div>{{ $order->email }}</div>@endif
        </td>
        <td>
            <div class="label">Ship to</div>
            @foreach($order->shippingAddressLines() as $line)<div>{{ $line }}</div>@endforeach
            @if($order->shipping_method)<div class="muted">via {{ $order->shipping_method }}</div>@endif
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width: 5%">#</th>
            <th>Item</th>
            <th style="width: 16%">SKU</th>
            <th class="right" style="width: 8%">Qty</th>
            <th class="right" style="width: 14%">Rate</th>
            @if($itemRates)<th class="right" style="width: 9%">{{ $taxLabel === 'CGST + SGST' ? 'GST' : $taxLabel }}</th>@endif
            <th class="right" style="width: 15%">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->name }}@if($opt = $item->optionsLabel())<div class="opt">{{ $opt }}</div>@endif</td>
                <td class="muted">{{ $item->sku ?: '—' }}</td>
                <td class="right">{{ $item->qty }}</td>
                <td class="right nowrap">{{ money($item->price) }}</td>
                @if($itemRates)<td class="right nowrap">{{ $item->tax_rate !== null ? \App\Models\Tax::rateLabel((float) $item->tax_rate) : '—' }}</td>@endif
                <td class="right nowrap">{{ money($item->total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td style="width: 55%"></td>
        <td style="width: 45%; padding: 0">
            <table>
                <tr><td class="muted">Subtotal</td><td class="right nowrap">{{ money($order->subtotal) }}</td></tr>
                @if($order->discount > 0)
                    <tr><td class="muted">Discount @if($order->coupon_code)({{ $order->coupon_code }})@endif</td><td class="right nowrap">− {{ money($order->discount) }}</td></tr>
                @endif
                <tr><td class="muted">Shipping &amp; handling</td><td class="right nowrap">{{ $order->shipping_cost > 0 ? money($order->shipping_cost) : 'Free' }}</td></tr>
                @if(! $inclusive)
                    @foreach($taxRows as $t)
                        <tr><td class="muted">{{ tax_line($t['label'], $t['rate'], false) }}</td><td class="right nowrap">{{ money($t['amount']) }}</td></tr>
                    @endforeach
                @endif
                <tr class="grand"><td>Total</td><td class="right nowrap">{{ money($order->total) }}</td></tr>
                @if($cancelledOn && in_array($order->payment_status, ['paid', 'refunded'], true))
                    <tr class="refund"><td>{{ $order->payment_status === 'refunded' ? 'Amount refunded' : 'Amount to be refunded' }}</td><td class="right nowrap">{{ money($order->total) }}</td></tr>
                @endif
                @if($inclusive && count($taxRows))
                    <tr><td class="muted">Taxable value</td><td class="right nowrap muted">{{ money($taxableValue) }}</td></tr>
                    @foreach($taxRows as $t)
                        <tr><td class="muted">{{ tax_line($t['label'], $t['rate'], true) }}</td><td class="right nowrap muted">{{ money($t['amount']) }}</td></tr>
                    @endforeach
                @endif
            </table>
        </td>
    </tr>
</table>

<div class="words"><span class="muted">Amount in words:</span> <strong>{{ $amountInWords }}</strong></div>

<div class="foot">
    @if($cancelledOn)<p class="cancel-note">This invoice was cancelled on {{ $cancelledOn->format('d M Y') }} and is not valid for payment or input tax credit.</p>@endif
    @if($seller['note'])<p style="margin: 0 0 6px">{{ $seller['note'] }}</p>@endif
    <p class="muted center" style="margin: 0">This is a computer-generated invoice and does not require a signature.</p>
</div>

</body>
</html>
