@extends('layouts.app')

{{--
    After checkout: the order number to quote, where it is going, every line with its code, and
    the next steps. Replaces the shared confirmation page.
--}}

@section('content')
<div class="bk-wrap mt-6">
    <div class="bk-card flex flex-wrap items-center gap-4 p-5 sm:p-6">
        <span class="bk-done-mark"><x-heroicon-m-check aria-hidden="true" /></span>
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-extrabold tracking-tight">Order placed — thank you</h1>
            <p class="bk-muted mt-1">
                Your order number is <span class="bk-code text-[var(--bk-ink)]">{{ $order->number }}</span>. A confirmation has been sent to {{ $order->email }}.
                @if($order->estimated_delivery) Expected {{ $order->estimated_delivery->format('l, d M') }}.@endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('track') }}?number={{ urlencode($order->number) }}&email={{ urlencode($order->email) }}" class="bk-btn bk-btn-outline"><x-heroicon-o-truck aria-hidden="true" />Track order</a>
            <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-primary"><x-heroicon-o-clipboard-document-list aria-hidden="true" />New bulk order</a>
        </div>
    </div>

    <div class="bk-card mt-5 p-5"><x-order-timeline :order="$order" /></div>

    <div class="mt-5 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <x-order-summary :order="$order" />
        <div class="space-y-5">
            <x-order-details :order="$order" />
            @guest
                <div class="bk-card p-5 text-sm">
                    <p class="font-extrabold">Ordering again soon?</p>
                    <p class="bk-muted mt-1">Create an account to see your orders and repeat any of them in one click.</p>
                    <a href="{{ route('register') }}" class="bk-btn bk-btn-outline mt-3">Create an account</a>
                </div>
            @endguest
        </div>
    </div>
</div>
@endsection
