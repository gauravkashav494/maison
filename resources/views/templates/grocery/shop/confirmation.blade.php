@extends('layouts.app')

@section('content')
<section class="g-container py-8 lg:py-12">
    <div class="mx-auto max-w-3xl">
        <div class="card p-6 text-center sm:p-8">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-leaf-light text-leaf"><x-ico name="check" :size="32" :stroke="3" /></span>
            <h1 class="mt-4 text-2xl font-extrabold sm:text-3xl">Order placed! 🎉</h1>
            <p class="mt-2 text-sm text-slate sm:text-base">Thanks, {{ Str::of($order->shipping_name)->before(' ') }}. Your order <strong class="text-ink">{{ $order->number }}</strong> is confirmed and being packed. We’ve emailed the details to {{ $order->email }}.</p>
            @if($order->estimated_delivery)<p class="mt-4 inline-flex items-center gap-2 rounded-full bg-saffron-light px-4 py-1.5 text-sm font-bold text-saffron-dark"><x-ico name="clock" :size="16" /> Arriving by {{ $order->estimated_delivery->format('l, d M') }}</p>@endif
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <a href="{{ route('track') }}?number={{ $order->number }}&email={{ urlencode($order->email) }}" class="btn btn-primary">Track order</a>
                <a href="{{ route('shop.index') }}" class="btn btn-ghost">Continue shopping</a>
            </div>
        </div>

        <div class="card mt-4 p-4 sm:p-5">
            <x-order-timeline :order="$order" />
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="card p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-mist">Deliver to</p>
                <div class="mt-2 text-sm leading-relaxed">@foreach($order->shippingAddressLines() as $l)<p>{{ $l }}</p>@endforeach<p class="text-slate">{{ $order->phone }}</p></div>
            </div>
            <div class="card p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-mist">Payment</p>
                <p class="mt-2 text-sm font-semibold">{{ \App\Models\Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }}</p>
                <p class="text-sm text-slate">{{ $order->payment_status === 'cod' ? 'Pay on delivery' : ucfirst($order->payment_status) }} · {{ money($order->total) }}</p>
                @if($order->notes)<p class="mt-3 text-xs text-slate"><strong>Instructions:</strong> {{ $order->notes }}</p>@endif
            </div>
        </div>

        <x-order-summary :order="$order" class="mt-4" />

        @guest
            <div class="card mt-4 flex flex-col items-center gap-3 p-5 text-center sm:flex-row sm:text-left">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-sky text-sky-dark"><x-ico name="user" :size="22" /></span>
                <div class="flex-1"><p class="text-sm font-bold">Create an account to reorder in one tap</p><p class="text-xs text-slate">Save addresses, track orders and get member-only offers.</p></div>
                <a href="{{ route('register') }}" class="btn btn-outline btn-sm">Sign up</a>
            </div>
        @endguest
    </div>
</section>
@endsection
