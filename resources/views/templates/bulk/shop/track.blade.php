@extends('layouts.app')

{{--
    Order tracking for buyers without an account (or not signed in): order number + email.
    Field names and the GET form match the shared tracking controller.
--}}

@section('content')
<div class="bk-wrap mt-6">
    <x-page-head title="Track an order" text="Enter the order number and the email used at checkout." :crumbs="['Track order' => null]" />

    <form method="get" action="{{ route('track') }}" class="bk-card mt-5 grid gap-4 p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end" novalidate>
        <label class="bk-label">Order number<span class="bk-req">*</span>
            <input name="number" value="{{ old('number', request('number')) }}" placeholder="e.g. ME-260916-XXXX" class="bk-input uppercase" autocomplete="off">
            @error('number')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <label class="bk-label">Email<span class="bk-req">*</span>
            <input name="email" type="email" value="{{ old('email', request('email')) }}" class="bk-input" autocomplete="email">
            @error('email')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <button type="submit" class="bk-btn bk-btn-primary !py-2.5"><x-heroicon-o-magnifying-glass aria-hidden="true" />Track</button>
        @if($error)<p class="bk-error sm:col-span-3" role="alert">{{ $error }}</p>@endif
    </form>

    @if($order)
        <div class="bk-card mt-5 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="bk-kicker">Order {{ $order->number }}</p>
                    <x-order-status :order="$order" />
                    <p class="bk-muted mt-2 text-sm">
                        Placed {{ $order->created_at->format('d M Y') }}
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
                @php $updates = collect($order->status_history ?? [])->reverse()->take(4); @endphp
                @if($updates->isNotEmpty())
                    <div>
                        <p class="bk-kicker">Updates</p>
                        <ul class="space-y-1.5">
                            @foreach($updates as $h)
                                <li><span class="bk-muted text-xs">{{ \Carbon\Carbon::parse($h['at'])->format('d M, H:i') }}</span> · {{ \App\Models\Order::STATUSES[$h['status']] ?? ucfirst($h['status']) }}@if(! empty($h['note']))<span class="bk-muted"> — {{ $h['note'] }}</span>@endif</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="bk-muted border-t border-[var(--bk-line)] pt-4 text-xs">Need help? <a href="{{ url('/contact') }}" class="bk-link">Contact the trade desk</a> with your order number.</p>
            </x-order-details>
        </div>
    @endif
</div>
@endsection
