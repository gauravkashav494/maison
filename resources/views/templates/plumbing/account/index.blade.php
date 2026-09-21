@extends('layouts.app')

@section('content')
<x-account-shell title="Hi, {{ Str::of($user->name)->before(' ') }} 👋">
    <div class="grid gap-3 sm:grid-cols-3">
        <a href="{{ route('account.orders') }}" class="card card-hover p-4"><x-ico name="package" :size="22" class="text-primary" /><p class="mt-2 font-display text-2xl font-bold">{{ $user->orders()->count() }}</p><p class="text-xs text-slate">Orders placed</p></a>
        <a href="{{ route('account.wishlist') }}" class="card card-hover p-4" x-data><x-ico name="heart" :size="22" class="text-danger" /><p class="mt-2 font-display text-2xl font-bold" x-text="$store.wishlist.count">0</p><p class="text-xs text-slate">Saved items</p></a>
        <a href="{{ route('account.addresses') }}" class="card card-hover p-4"><x-ico name="pin" :size="22" class="text-accent-dark" /><p class="mt-2 font-display text-2xl font-bold">{{ $user->addresses()->count() }}</p><p class="text-xs text-slate">Saved addresses</p></a>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="card p-4 sm:p-5">
            <div class="flex items-center justify-between"><p class="text-sm font-extrabold">Recent orders</p><a href="{{ route('account.orders') }}" class="sec-link text-xs">View all</a></div>
            @if($orders->isEmpty())
                <p class="mt-3 text-sm text-slate">You haven’t ordered yet. <a href="{{ route('shop.index') }}" class="font-semibold text-primary">Start shopping</a></p>
            @else
                <ul class="mt-3 divide-y divide-line">
                    @foreach($orders as $order)
                        <li class="py-3 first:pt-0 last:pb-0">
                            <a href="{{ route('account.order', $order->number) }}" class="flex items-center gap-3">
                                <div class="flex -space-x-3">@foreach($order->items->take(3) as $item)<span class="h-10 w-10 overflow-hidden rounded-lg border-2 border-white bg-canvas">@if($item->image)<img src="{{ \App\Support\Media::url($item->image) }}" alt="" class="img-cover">@endif</span>@endforeach</div>
                                <div class="min-w-0 flex-1"><p class="text-sm font-bold">{{ $order->number }}</p><p class="text-xs text-slate">{{ $order->created_at->format('d M Y') }} · {{ $order->items->sum('qty') }} items</p></div>
                                <div class="text-right"><p class="text-sm font-bold tabular">{{ money($order->total) }}</p><span class="badge badge-out">{{ $order->statusLabel() }}</span></div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="space-y-4">
            <div class="card p-4 sm:p-5">
                <div class="flex items-center justify-between"><p class="text-sm font-extrabold">Default address</p><a href="{{ route('account.addresses') }}" class="sec-link text-xs">Manage</a></div>
                @if($address)<div class="mt-2 text-sm leading-relaxed text-slate">@foreach($address->lines() as $l)<p>{{ $l }}</p>@endforeach</div>@else<p class="mt-2 text-sm text-slate">No address saved yet.</p>@endif
            </div>
            <div class="card p-4 sm:p-5">
                <div class="flex items-center justify-between"><p class="text-sm font-extrabold">Profile</p><a href="{{ route('account.profile') }}" class="sec-link text-xs">Edit</a></div>
                <p class="mt-2 text-sm">{{ $user->name }}</p><p class="text-sm text-slate">{{ $user->email }}@if($user->phone) · {{ $user->phone }}@endif</p>
            </div>
        </div>
    </div>
</x-account-shell>
@endsection
