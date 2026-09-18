@extends('layouts.app')

@section('content')
<x-page-head title="Track your order" text="Enter your order number and the email used at checkout." :breadcrumbs="['Track order' => null]" />

<section class="g-container py-6">
    <div class="mx-auto max-w-3xl space-y-4">
        <form method="get" action="{{ route('track') }}" class="card grid gap-3 p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end sm:p-5">
            <label><span class="label">Order number</span><input name="number" required value="{{ request('number') }}" placeholder="e.g. ME-2026-00123" class="field uppercase"></label>
            <label><span class="label">Email</span><input name="email" type="email" required value="{{ request('email') }}" class="field"></label>
            <button type="submit" class="btn btn-primary h-[46px]">Track</button>
            @if($error)<p class="error-text sm:col-span-3">{{ $error }}</p>@endif
        </form>

        @if($order)
            <div class="card p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-mist">Order {{ $order->number }}</p>
                        <p class="mt-1 text-lg font-extrabold">{{ $order->statusLabel() }}</p>
                        <p class="text-xs text-slate">Placed {{ $order->created_at->format('d M Y, H:i') }}@if($order->estimated_delivery) · Expected by {{ $order->estimated_delivery->format('d M') }}@endif</p>
                    </div>
                    @if($order->tracking_number)<div class="rounded-lg bg-paper px-3 py-2 text-xs"><span class="text-slate">{{ $order->carrier ?: 'Tracking' }}</span><span class="ml-2 font-bold tabular">{{ $order->tracking_number }}</span></div>@endif
                </div>
                <div class="mt-6"><x-order-timeline :order="$order" /></div>
                @php $history = collect($order->status_history ?? [])->reverse(); @endphp
                @if($history->isNotEmpty())
                    <ul class="mt-6 space-y-2 border-t border-line pt-4 text-sm">
                        @foreach($history as $h)
                            <li class="flex gap-3"><span class="w-28 shrink-0 text-xs text-mist">{{ \Carbon\Carbon::parse($h['at'])->format('d M, H:i') }}</span><span><strong>{{ \App\Models\Order::STATUSES[$h['status']] ?? ucfirst($h['status']) }}</strong>@if(!empty($h['note'])) <span class="text-slate">— {{ $h['note'] }}</span>@endif</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <x-order-summary :order="$order" />
        @else
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="card p-4 text-sm"><x-ico name="receipt" :size="20" class="text-leaf" /><p class="mt-2 font-bold">Where’s my order number?</p><p class="text-xs text-slate">It’s in your confirmation email and on the order page, e.g. ME-2026-00123.</p></div>
                <div class="card p-4 text-sm"><x-ico name="user" :size="20" class="text-leaf" /><p class="mt-2 font-bold">Have an account?</p><p class="text-xs text-slate"><a href="{{ route('account.orders') }}" class="font-semibold text-leaf">Log in</a> to see every order and its live status.</p></div>
                <div class="card p-4 text-sm"><x-ico name="phone" :size="20" class="text-leaf" /><p class="mt-2 font-bold">Need help?</p><p class="text-xs text-slate"><a href="/contact" class="font-semibold text-leaf">Contact support</a> — we reply within a few hours.</p></div>
            </div>
        @endif
    </div>
</section>
@endsection
