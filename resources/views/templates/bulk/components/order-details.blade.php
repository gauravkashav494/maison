{{--
    A placed order's delivery address, delivery method, payment and notes, as one card. Shared by
    the account order page, order tracking and the confirmation page (which passes
    :invoice="false", having its own Download invoice button).
--}}
@props(['order', 'heading' => 'Delivering to', 'invoice' => true])

<div {{ $attributes->merge(['class' => 'bk-card space-y-3 p-4 text-sm']) }}>
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
        @if($invoice && $order->hasInvoice())<a href="{{ $order->invoiceUrl() }}" class="bk-link mt-1 inline-flex items-center gap-1 text-sm"><x-heroicon-o-document-arrow-down class="h-4 w-4" aria-hidden="true" />Download invoice</a>@endif
    </div>
    @if($order->notes)
        <div><p class="bk-kicker">Your reference / notes</p><p class="bk-muted">{{ $order->notes }}</p></div>
    @endif
    {{ $slot }}
</div>
