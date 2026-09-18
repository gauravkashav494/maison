@extends('layouts.app')

@section('content')
<x-account-shell title="Order {{ $order->number }}">
    <div class="space-y-4">
        <div class="card p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-lg font-semibold">{{ $order->statusLabel() }}</p><p class="text-xs text-muted">Placed {{ $order->created_at->format('d M Y, H:i') }}@if($order->estimated_delivery) · Expected by {{ $order->estimated_delivery->format('d M') }}@endif</p></div>
                <div class="flex gap-2">
                    @if($order->tracking_number)<span class="rounded-lg bg-cream px-3 py-2 text-xs"><span class="text-muted">{{ $order->carrier ?: 'Tracking' }}</span> <strong class="tabular">{{ $order->tracking_number }}</strong></span>@endif
                    <a href="{{ route('track') }}?number={{ $order->number }}&email={{ urlencode($order->email) }}" class="btn btn-ghost btn-sm">Track</a>
                </div>
            </div>
            <div class="mt-6"><x-order-timeline :order="$order" /></div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="card p-4"><p class="text-xs font-bold uppercase tracking-wider text-muted">Delivered to</p><div class="mt-2 text-sm leading-relaxed">@foreach($order->shippingAddressLines() as $l)<p>{{ $l }}</p>@endforeach<p class="text-muted">{{ $order->phone }}</p></div></div>
            <div class="card p-4"><p class="text-xs font-bold uppercase tracking-wider text-muted">Payment</p><p class="mt-2 text-sm font-semibold">{{ \App\Models\Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }}</p><p class="text-sm text-muted">{{ $order->payment_status === 'cod' ? 'Pay on delivery' : ucfirst($order->payment_status) }}</p>@if($order->notes)<p class="mt-2 text-xs text-muted"><strong>Instructions:</strong> {{ $order->notes }}</p>@endif</div>
        </div>
        <x-order-summary :order="$order" />
        <div class="card flex flex-col items-start gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm font-bold">Need help with this order?</p><p class="text-xs text-muted">Missing or damaged items? Report within 24 hours of delivery.</p></div>
            <a href="/contact" class="btn btn-outline btn-sm">Contact support</a>
        </div>
    </div>
</x-account-shell>
@endsection
