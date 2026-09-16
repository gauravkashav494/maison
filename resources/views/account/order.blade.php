@extends('layouts.app')

@section('content')
<x-account-shell :title="'Order ' . $order->number" eyebrow="Order details">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="font-serif text-3xl">{{ $order->statusLabel() }}</p>
            <p class="mt-1 text-sm text-smoke">Placed {{ $order->created_at->format('d F Y, H:i') }}@if($order->estimated_delivery && !in_array($order->status, ['delivered', 'cancelled'])) · Estimated delivery {{ $order->estimated_delivery->format('l, d F') }}@endif</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @if($order->tracking_number)<span class="border border-ink/15 px-3 py-2 text-xs">{{ $order->carrier ?: 'Tracking' }}: <span class="font-mono">{{ $order->tracking_number }}</span></span>@endif
            @if($order->status === 'delivered')<a href="/returns" class="btn btn-outline btn-sm">Start a return</a>@endif
        </div>
    </div>

    <div class="py-10"><x-order-timeline :order="$order" /></div>

    <div class="grid gap-10 lg:grid-cols-5 lg:gap-16">
        <div class="space-y-8 text-sm lg:col-span-2">
            <div><p class="eyebrow mb-2 text-taupe">Shipping address</p>@foreach($order->shippingAddressLines() as $l)<p>{{ $l }}</p>@endforeach<p class="mt-1 text-smoke">{{ $order->phone }}</p></div>
            <div><p class="eyebrow mb-2 text-taupe">Delivery</p><p>{{ $order->shipping_method }}</p></div>
            <div><p class="eyebrow mb-2 text-taupe">Payment</p><p>{{ \App\Models\Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }} · {{ $order->payment_status === 'cod' ? 'Pay on delivery' : ucfirst($order->payment_status) }}</p></div>
            @if($order->notes)<div><p class="eyebrow mb-2 text-taupe">Notes</p><p class="text-smoke">{{ $order->notes }}</p></div>@endif
            <p class="text-xs text-smoke">Questions? <a href="/contact" class="underline underline-offset-4">Contact client care</a> quoting {{ $order->number }}.</p>
        </div>
        <x-order-summary :order="$order" class="lg:col-span-3" />
    </div>
</x-account-shell>
@endsection
