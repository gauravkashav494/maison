@extends('layouts.app')

@section('content')
<x-account-shell title="My orders">
    @if($orders->isEmpty())
        <x-empty-state icon="package" title="No orders yet" text="Your orders will show up here once you place them.">
            <a href="{{ route('shop.index') }}" class="btn btn-primary">Start shopping</a>
        </x-empty-state>
    @else
        <div class="space-y-3">
            @foreach($orders as $order)
                <a href="{{ route('account.order', $order->number) }}" class="card card-hover block p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div><p class="text-sm font-semibold">{{ $order->number }}</p><p class="text-xs text-muted">{{ $order->created_at->format('d M Y, H:i') }} · {{ $order->items->sum('qty') }} items · {{ \App\Models\Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }}</p></div>
                        <div class="flex items-center gap-3"><span class="badge {{ $order->status === 'delivered' ? 'badge-soft' : ($order->status === 'cancelled' ? 'badge-off' : 'badge-soft') }}">{{ $order->statusLabel() }}</span><span class="text-sm font-semibold tabular">{{ money($order->total) }}</span></div>
                    </div>
                    <div class="mt-3 flex gap-2 overflow-hidden">
                        @foreach($order->items->take(6) as $item)<span class="h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-line bg-cream">@if($item->image)<img src="{{ \App\Support\Media::url($item->image) }}" alt="" class="img-cover">@endif</span>@endforeach
                        @if($order->items->count() > 6)<span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-cream text-xs font-bold text-muted">+{{ $order->items->count() - 6 }}</span>@endif
                    </div>
                </a>
            @endforeach
        </div>
        @if($orders->hasPages())<div class="mt-6">{{ $orders->links('vendor.pagination.heritage') }}</div>@endif
    @endif
</x-account-shell>
@endsection
