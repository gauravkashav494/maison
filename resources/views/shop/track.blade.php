@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
<x-page-hero eyebrow="Order tracking" title="Track your order" description="Enter your order number and the email used at checkout to see where your parcel is." :breadcrumbs="['Track order' => null]" />

<section class="container-luxe py-12 lg:py-16">
    <form method="get" action="{{ route('track') }}" class="mx-auto grid max-w-2xl gap-6 border border-ink/10 bg-cream p-6 sm:grid-cols-[1fr_1fr_auto] sm:items-end lg:p-8">
        <label><span class="block text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Order number</span><input name="number" required value="{{ request('number') }}" placeholder="ME-260916-XXXX" class="input-luxe uppercase"></label>
        <label><span class="block text-[0.6875rem] uppercase tracking-[0.2em] text-smoke">Email</span><input name="email" type="email" required value="{{ request('email') }}" class="input-luxe"></label>
        <button type="submit" class="btn btn-primary">Track</button>
        @if($error)<p class="text-sm text-rouge sm:col-span-3">{{ $error }}</p>@endif
    </form>

    @if($order)
        <div class="mx-auto mt-16 max-w-4xl">
            <div class="flex flex-col gap-2 border-b border-ink/10 pb-8 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow text-taupe">Order {{ $order->number }}</p>
                    <h2 class="mt-2 font-serif text-4xl">{{ $order->statusLabel() }}</h2>
                    <p class="mt-2 text-sm text-smoke">Placed {{ $order->created_at->format('d F Y') }}@if($order->estimated_delivery && $order->status !== 'delivered') · Estimated delivery {{ $order->estimated_delivery->format('l, d F') }}@endif</p>
                </div>
                @if($order->tracking_number)
                    <div class="text-sm sm:text-right"><p class="eyebrow text-taupe">{{ $order->carrier ?: 'Tracking number' }}</p><p class="mt-1 font-mono">{{ $order->tracking_number }}</p></div>
                @endif
            </div>
            <div class="py-10"><x-order-timeline :order="$order" /></div>
            <div class="grid gap-10 lg:grid-cols-5 lg:gap-16">
                <div class="space-y-8 text-sm lg:col-span-2">
                    <div><p class="eyebrow mb-2 text-taupe">Shipping address</p>@foreach($order->shippingAddressLines() as $line)<p>{{ $line }}</p>@endforeach</div>
                    <div><p class="eyebrow mb-2 text-taupe">Delivery</p><p>{{ $order->shipping_method }}</p></div>
                    @if($history = collect($order->status_history)->reverse()->take(4))
                        <div><p class="eyebrow mb-2 text-taupe">Updates</p><ul class="space-y-2">@foreach($history as $h)<li class="flex gap-3"><span class="w-24 shrink-0 text-xs text-taupe">{{ \Carbon\Carbon::parse($h['at'])->format('d M, H:i') }}</span><span>{{ \App\Models\Order::STATUSES[$h['status']] ?? ucfirst($h['status']) }}@if(!empty($h['note'])) <span class="text-smoke">— {{ $h['note'] }}</span>@endif</span></li>@endforeach</ul></div>
                    @endif
                    <p class="text-xs text-smoke">Need help? <a href="/contact" class="underline underline-offset-4">Contact client care</a> with your order number.</p>
                </div>
                <x-order-summary :order="$order" class="lg:col-span-3" />
            </div>
        </div>
    @endif
</section>
@endsection
