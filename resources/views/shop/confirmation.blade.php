@extends('layouts.app')

@section('content')
<section class="container-luxe pt-24 lg:pt-44">
    <div class="mx-auto max-w-3xl text-center">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full border border-ink"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12.5 9.5 17 19 7"/></svg></span>
        <p class="eyebrow mt-6 text-taupe">Order confirmed</p>
        <h1 class="display-md mt-4 text-balance">Thank you, {{ explode(' ', $order->shipping_name)[0] }}.</h1>
        <p class="mx-auto mt-5 max-w-lg text-[0.9375rem] leading-relaxed text-smoke">Your order <span class="text-ink">{{ $order->number }}</span> has been placed. A confirmation has been sent to {{ $order->email }}. Estimated delivery {{ $order->estimated_delivery?->format('l, d F') }}.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-4">
            <a href="{{ route('track') }}?number={{ $order->number }}&email={{ urlencode($order->email) }}" class="btn btn-primary">Track order</a>
            <a href="{{ route('shop.index') }}" class="btn btn-outline">Continue shopping</a>
        </div>
    </div>
</section>

<section class="container-luxe py-16 lg:py-20">
    <div class="mx-auto max-w-4xl">
        <x-order-timeline :order="$order" />
        <div class="mt-14 grid gap-10 lg:grid-cols-5 lg:gap-16">
            <div class="space-y-8 text-sm lg:col-span-2">
                <div><p class="eyebrow mb-2 text-taupe">Delivering to</p>@foreach($order->shippingAddressLines() as $line)<p>{{ $line }}</p>@endforeach</div>
                <div><p class="eyebrow mb-2 text-taupe">Delivery</p><p>{{ $order->shipping_method }}</p></div>
                <div><p class="eyebrow mb-2 text-taupe">Payment</p><p>{{ \App\Models\Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }} · {{ $order->payment_status === 'cod' ? 'Pay on delivery' : ucfirst($order->payment_status) }}</p></div>
                @guest<div class="border border-ink/10 bg-cream p-5"><p class="font-serif text-lg">Save your details</p><p class="mt-1 text-xs text-smoke">Create an account to track orders, save addresses and keep a wishlist.</p><a href="{{ route('register') }}" class="link-underline eyebrow mt-3 inline-block">Create account →</a></div>@endguest
            </div>
            <x-order-summary :order="$order" class="lg:col-span-3" />
        </div>
    </div>
</section>
@endsection
