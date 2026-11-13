{{--
    A placed order's lines and totals, used by the shared confirmation, tracking and account
    order pages. Each line keeps the code it was ordered under — what a buyer checks a delivery
    against — even if the product changes later.
--}}
@props(['order'])

<div {{ $attributes->merge(['class' => 'bk-card p-5']) }}>
    <p class="text-lg font-extrabold">Items ordered</p>
    <table class="bk-table is-stack mt-2">
        <thead>
            <tr>
                <th scope="col">Item</th>
                <th scope="col">Code</th>
                <th scope="col" class="is-num">Qty</th>
                <th scope="col" class="is-num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td class="font-bold">
                        {{ $item->name }}
                        @if($label = $item->optionsLabel())<span class="bk-muted block text-xs font-normal">{{ $label }}</span>@endif
                    </td>
                    <td class="bk-code" data-label="Code">{{ $item->variant_sku ?: ($item->sku ?: '—') }}</td>
                    <td class="is-num" data-label="Qty">{{ $item->qty }} × {{ money($item->price) }}</td>
                    <td class="is-num font-bold" data-label="Amount">{{ money($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <dl class="bk-totals mt-4">
        <dt>Subtotal</dt><dd>{{ money($order->subtotal) }}</dd>
        @if($order->discount)
            <dt>Discount{{ $order->coupon_code ? " ({$order->coupon_code})" : '' }}</dt><dd>− {{ money($order->discount) }}</dd>
        @endif
        <dt>Delivery{{ $order->shipping_method ? " · {$order->shipping_method}" : '' }}</dt><dd>{{ $order->shipping_cost ? money($order->shipping_cost) : 'Free' }}</dd>
        @if($order->tax)<dt>GST</dt><dd>{{ money($order->tax) }}</dd>@endif
        <dt class="is-total">Total</dt><dd class="is-total">{{ money($order->total) }}</dd>
    </dl>
</div>
