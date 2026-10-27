{{--
    The placed order, as a table. The shared component draws a picture box for every line and
    leaves it empty when a product has no image — on this template that is every line, so the
    summary is rebuilt here from the same data without any imagery, matching the order list.
--}}
@props(['order'])

<div {{ $attributes }}>
    <table class="ol-table">
        <thead>
            <tr>
                <th scope="col">Product</th>
                <th scope="col">Code</th>
                <th scope="col">Quantity</th>
                <th scope="col" class="ol-price">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td class="ol-name">
                        {{ $item->name }}
                        @if($label = $item->optionsLabel())<span class="ol-sub">{{ $label }}</span>@endif
                    </td>
                    {{-- The code is snapshotted on the line, so it stays right if the product changes later. --}}
                    <td class="ol-code" data-label="Code">{{ $item->variant_sku ?: ($item->sku ?: '—') }}</td>
                    <td data-label="Quantity">{{ $item->qty }} × {{ money($item->price) }}</td>
                    <td class="ol-price" data-label="Line total">{{ money($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <dl class="ol-detail" style="gap:.4rem 1.25rem;margin-top:1.25rem">
        <dt>Subtotal</dt><dd>{{ money($order->subtotal) }}</dd>
        @if($order->discount)
            <dt>Discount{{ $order->coupon_code ? " ({$order->coupon_code})" : '' }}</dt>
            <dd>− {{ money($order->discount) }}</dd>
        @endif
        <dt>Delivery{{ $order->shipping_method ? " · {$order->shipping_method}" : '' }}</dt>
        <dd>{{ $order->shipping_cost ? money($order->shipping_cost) : 'Free' }}</dd>
        @if($order->tax)
            <dt>Tax</dt><dd>{{ money($order->tax) }}</dd>
        @endif
        <dt><strong>Total</strong></dt><dd><strong>{{ money($order->total) }}</strong></dd>
    </dl>
</div>
