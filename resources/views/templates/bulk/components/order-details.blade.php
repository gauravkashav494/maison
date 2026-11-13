{{--
    A placed order's delivery address, delivery method, payment and notes, as one card. Shared by
    the account order page, order tracking and the confirmation page.
--}}
@props(['order', 'heading' => 'Delivering to'])

<div {{ $attributes->merge(['class' => 'bk-card space-y-4 p-5 text-sm']) }}>
    <div>
        <p class="bk-kicker">{{ $heading }}</p>
        @foreach($order->shippingAddressLines() as $line)<p>{{ $line }}</p>@endforeach
        @if($order->phone)<p class="bk-muted mt-1">{{ $order->phone }}</p>@endif
    </div>
    @if($order->shipping_method)
        <div><p class="bk-kicker">Delivery</p><p>{{ $order->shipping_method }}</p></div>
    @endif
    <div>
        <p class="bk-kicker">Payment</p>
        <p>{{ \App\Models\Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }} · {{ $order->payment_status === 'cod' ? 'Pay on delivery' : ucfirst((string) $order->payment_status) }}</p>
    </div>
    @if($order->notes)
        <div><p class="bk-kicker">Your reference / notes</p><p class="bk-muted">{{ $order->notes }}</p></div>
    @endif
    {{ $slot }}
</div>
