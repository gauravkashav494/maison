@extends('layouts.app')

{{--
    After checkout: the order number to quote, where it is going, every line with its code, and
    the next steps. Replaces the shared confirmation page.
--}}

@section('content')
<div class="bk-compact bk-wrap mt-6">
    <div class="bk-card flex flex-wrap items-center gap-3 p-4">
        <span class="bk-done-mark"><x-heroicon-m-check aria-hidden="true" /></span>
        <div class="min-w-0 flex-1">
            <h1 class="text-xl font-extrabold tracking-tight">Order placed — thank you</h1>
            <p class="bk-muted mt-0.5 text-sm">
                Your order number is <span class="bk-code text-[var(--bk-ink)]">{{ $order->number }}</span>. @if($order->email)A confirmation has been sent to {{ $order->email }}.@endif
                @if($order->estimated_delivery) Expected {{ $order->estimated_delivery->format('l, d M') }}.@endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('track') }}?number={{ urlencode($order->number) }}&contact={{ urlencode($order->email ?: $order->phone) }}" class="bk-btn bk-btn-outline"><x-heroicon-o-truck aria-hidden="true" />Track order</a>
            @if($order->hasInvoice())<a href="{{ $order->invoiceUrl() }}" class="bk-btn bk-btn-outline"><x-heroicon-o-document-arrow-down aria-hidden="true" />Download invoice</a>@endif
            <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-primary"><x-heroicon-o-clipboard-document-list aria-hidden="true" />New bulk order</a>
        </div>
    </div>

    <div class="bk-card mt-4 p-4"><x-order-timeline :order="$order" /></div>
    <x-order-cancel :order="$order" class="mt-4" />

    <div class="mt-4 grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <x-order-summary :order="$order" />
        <div class="space-y-4">
            <x-order-details :order="$order" :invoice="false" />
            @guest
                <div class="bk-card p-4 text-sm">
                    <p class="font-extrabold">Ordering again soon?</p>
                    <p class="bk-muted mt-1">Create an account to see your orders and repeat any of them in one click.</p>
                    <a href="{{ route('register') }}" class="bk-btn bk-btn-outline mt-3">Create an account</a>
                </div>
            @endguest
        </div>
    </div>
</div>
@endsection
