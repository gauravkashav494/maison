@extends('layouts.app')

@section('content')
<x-account-shell :title="'Order '.$order->number">
    <x-slot:actions>
        <a href="{{ route('account.orders') }}" class="bk-link text-sm">← All orders</a>
    </x-slot:actions>

    <div class="bk-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <x-order-status :order="$order" />
                <p class="bk-muted mt-2 text-sm">
                    Placed {{ $order->created_at->format('d M Y, H:i') }}
                    @if($order->estimated_delivery && ! in_array($order->status, ['delivered', 'cancelled'])) · Expected {{ $order->estimated_delivery->format('D, d M') }}@endif
                </p>
            </div>
            @if($order->tracking_number)
                <p class="text-sm sm:text-right"><span class="bk-kicker">{{ $order->carrier ?: 'Tracking number' }}</span><span class="bk-code">{{ $order->tracking_number }}</span></p>
            @endif
        </div>
        <div class="mt-5"><x-order-timeline :order="$order" /></div>
    </div>

    <div class="mt-5 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <x-order-summary :order="$order" />
        <x-order-details :order="$order">
            <p class="bk-muted border-t border-[var(--bk-line)] pt-4 text-xs">Questions about this order? <a href="{{ url('/contact') }}" class="bk-link">Contact the trade desk</a> quoting {{ $order->number }}.</p>
        </x-order-details>
    </div>
</x-account-shell>
@endsection
