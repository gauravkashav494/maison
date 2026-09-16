@extends('layouts.app')

@section('content')
<x-account-shell :title="'Welcome, ' . $user->firstName()">
    <div class="grid gap-6 sm:grid-cols-3">
        <a href="{{ route('account.orders') }}" class="group border border-ink/10 bg-cream p-6"><p class="eyebrow text-taupe">Orders</p><p class="mt-3 font-serif text-4xl">{{ $user->orders()->count() }}</p><span class="link-underline mt-3 inline-block text-[0.625rem] uppercase tracking-[0.2em]">View all →</span></a>
        <a href="{{ route('account.wishlist') }}" class="group border border-ink/10 bg-cream p-6" x-data><p class="eyebrow text-taupe">Wishlist</p><p class="mt-3 font-serif text-4xl" x-text="$store.wishlist.count">0</p><span class="link-underline mt-3 inline-block text-[0.625rem] uppercase tracking-[0.2em]">View →</span></a>
        <a href="{{ route('account.addresses') }}" class="group border border-ink/10 bg-cream p-6"><p class="eyebrow text-taupe">Addresses</p><p class="mt-3 font-serif text-4xl">{{ $user->addresses()->count() }}</p><span class="link-underline mt-3 inline-block text-[0.625rem] uppercase tracking-[0.2em]">Manage →</span></a>
    </div>

    <div class="mt-14">
        <div class="flex items-end justify-between"><h2 class="font-serif text-2xl">Recent orders</h2><a href="{{ route('account.orders') }}" class="link-underline eyebrow">All orders</a></div>
        @if($orders->isEmpty())
            <div class="mt-6 border border-dashed border-ink/15 p-10 text-center text-sm text-smoke">You have not placed an order yet. <a href="{{ route('shop.index') }}" class="underline underline-offset-4">Start shopping</a>.</div>
        @else
            <ul class="mt-6 divide-y divide-ink/10 border-y border-ink/10">
                @foreach($orders as $order)
                    <li><a href="{{ route('account.order', $order->number) }}" class="flex flex-wrap items-center gap-4 py-5 hover:bg-cream/60 sm:gap-6">
                        <div class="flex -space-x-3">@foreach($order->items->take(3) as $item)<span class="relative h-14 w-11 overflow-hidden border-2 border-ivory bg-sand">@if($item->image_url)<img src="{{ $item->image_url }}" alt="" class="img-cover">@endif</span>@endforeach</div>
                        <div class="min-w-0 flex-1"><p class="font-serif text-lg">{{ $order->number }}</p><p class="text-xs text-smoke">{{ $order->created_at->format('d M Y') }} · {{ $order->items->sum('qty') }} items</p></div>
                        <span class="text-[0.625rem] uppercase tracking-[0.2em] {{ $order->status === 'delivered' ? 'text-emerald-700' : ($order->status === 'cancelled' ? 'text-rouge' : 'text-ink') }}">{{ $order->statusLabel() }}</span>
                        <span class="tabular-nums">{{ money($order->total) }}</span>
                    </a></li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-14 grid gap-6 sm:grid-cols-2">
        <div class="border border-ink/10 p-6">
            <div class="flex items-center justify-between"><h3 class="font-serif text-xl">Default address</h3><a href="{{ route('account.addresses') }}" class="link-underline text-[0.625rem] uppercase tracking-[0.2em]">Edit</a></div>
            @if($address)<div class="mt-4 text-sm leading-relaxed text-smoke">@foreach($address->lines() as $l)<p>{{ $l }}</p>@endforeach</div>@else<p class="mt-4 text-sm text-smoke">No address saved yet.</p>@endif
        </div>
        <div class="border border-ink/10 p-6">
            <div class="flex items-center justify-between"><h3 class="font-serif text-xl">Account details</h3><a href="{{ route('account.profile') }}" class="link-underline text-[0.625rem] uppercase tracking-[0.2em]">Edit</a></div>
            <div class="mt-4 text-sm leading-relaxed text-smoke"><p>{{ $user->name }}</p><p>{{ $user->email }}</p>@if($user->phone)<p>{{ $user->phone }}</p>@endif</div>
        </div>
    </div>
</x-account-shell>
@endsection
