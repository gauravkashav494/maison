@extends('layouts.app')

{{--
    Account overview: counts, recent orders and the details checkout fills in. Replaces the
    shared overview.
--}}

@section('content')
<x-account-shell :title="'Welcome, '.$user->firstName()" text="Your orders, delivery addresses and account details.">
    <x-slot:actions>
        <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-primary"><x-heroicon-o-clipboard-document-list aria-hidden="true" />New bulk order</a>
    </x-slot:actions>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('account.orders') }}" class="bk-card bk-stat">
            <span class="bk-icon-tile"><x-heroicon-o-clipboard-document-list /></span>
            <span><span class="bk-stat-num">{{ $user->orders()->count() }}</span><span class="bk-muted block text-xs">Orders</span></span>
        </a>
        <a href="{{ route('account.addresses') }}" class="bk-card bk-stat">
            <span class="bk-icon-tile"><x-heroicon-o-map-pin /></span>
            <span><span class="bk-stat-num">{{ $user->addresses()->count() }}</span><span class="bk-muted block text-xs">Saved addresses</span></span>
        </a>
        <a href="{{ route('account.wishlist') }}" class="bk-card bk-stat" x-data>
            <span class="bk-icon-tile"><x-heroicon-o-heart /></span>
            <span><span class="bk-stat-num" x-text="$store.wishlist.count">0</span><span class="bk-muted block text-xs">Saved items</span></span>
        </a>
        <a href="{{ url('/#bulk-order') }}" class="bk-card bk-stat">
            <span class="bk-icon-tile"><x-heroicon-o-arrow-path /></span>
            <span><span class="block text-sm font-bold">Order again</span><span class="bk-muted block text-xs">Repeat a past order</span></span>
        </a>
    </div>

    <section class="bk-card mt-4 p-4">
        <div class="bk-section-head !mb-2">
            <div><h2 class="!text-base">Recent orders</h2></div>
            @if($orders->isNotEmpty())<a href="{{ route('account.orders') }}" class="bk-link text-sm">All orders →</a>@endif
        </div>
        @include('account.partials.order-table', ['orders' => $orders])
    </section>

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <section class="bk-card p-4 text-sm">
            <div class="flex items-center justify-between"><h2 class="font-extrabold">Default address</h2><a href="{{ route('account.addresses') }}" class="bk-link">Edit</a></div>
            @if($address)
                <div class="bk-muted mt-2 leading-snug">@foreach($address->lines() as $line)<p>{{ $line }}</p>@endforeach</div>
            @else
                <p class="bk-muted mt-2">No address saved yet. <a href="{{ route('account.addresses') }}" class="bk-link">Add one</a> to check out faster.</p>
            @endif
        </section>
        <section class="bk-card p-4 text-sm">
            <div class="flex items-center justify-between"><h2 class="font-extrabold">Account details</h2><a href="{{ route('account.profile') }}" class="bk-link">Edit</a></div>
            <div class="bk-muted mt-2 leading-snug"><p>{{ $user->name }}</p><p>{{ $user->email }}</p>@if($user->phone)<p>{{ $user->phone }}</p>@endif</div>
        </section>
    </div>
</x-account-shell>
@endsection
