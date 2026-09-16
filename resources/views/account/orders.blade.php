@extends('layouts.app')

@section('content')
<x-account-shell title="My orders">
    @if($orders->isEmpty())
        <div class="border border-dashed border-ink/15 p-10 text-center text-sm text-smoke">You have not placed an order yet. <a href="{{ route('shop.index') }}" class="underline underline-offset-4">Start shopping</a>.</div>
    @else
        <ul class="divide-y divide-ink/10 border-y border-ink/10">
            @foreach($orders as $order)
                <li><a href="{{ route('account.order', $order->number) }}" class="flex flex-wrap items-center gap-4 py-5 hover:bg-cream/60 sm:gap-6">
                    <div class="flex -space-x-3">@foreach($order->items->take(3) as $item)<span class="relative h-16 w-12 overflow-hidden border-2 border-ivory bg-sand">@if($item->image_url)<img src="{{ $item->image_url }}" alt="" class="img-cover">@endif</span>@endforeach</div>
                    <div class="min-w-0 flex-1"><p class="font-serif text-lg">{{ $order->number }}</p><p class="text-xs text-smoke">{{ $order->created_at->format('d M Y') }} · {{ $order->items->sum('qty') }} items · {{ \App\Models\Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }}</p></div>
                    <span class="text-[0.625rem] uppercase tracking-[0.2em] {{ $order->status === 'delivered' ? 'text-emerald-700' : ($order->status === 'cancelled' ? 'text-rouge' : 'text-ink') }}">{{ $order->statusLabel() }}</span>
                    <span class="tabular-nums">{{ money($order->total) }}</span>
                    <x-ico name="arrow-right" :size="14" class="text-taupe" />
                </a></li>
            @endforeach
        </ul>
        <div class="mt-8">{{ $orders->links() }}</div>
    @endif
</x-account-shell>
@endsection
