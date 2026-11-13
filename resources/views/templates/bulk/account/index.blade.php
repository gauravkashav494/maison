@extends('layouts.app')

{{--
    Account overview: counts, recent orders and the details checkout fills in. Replaces the
    shared overview; there is no wishlist in this store.
--}}

@section('content')
<x-account-shell :title="'Welcome, '.$user->firstName()" text="Your orders, delivery addresses and account details.">
    <x-slot:actions>
        <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-primary"><x-heroicon-o-clipboard-document-list aria-hidden="true" />New bulk order</a>
    </x-slot:actions>

    <div class="grid gap-3 sm:grid-cols-3">
        <a href="{{ route('account.orders') }}" class="bk-card bk-stat">
            <span class="bk-icon-tile"><x-heroicon-o-clipboard-document-list /></span>
            <span><span class="bk-stat-num">{{ $user->orders()->count() }}</span><span class="bk-muted block text-sm">Orders</span></span>
        </a>
        <a href="{{ route('account.addresses') }}" class="bk-card bk-stat">
            <span class="bk-icon-tile"><x-heroicon-o-map-pin /></span>
            <span><span class="bk-stat-num">{{ $user->addresses()->count() }}</span><span class="bk-muted block text-sm">Saved addresses</span></span>
        </a>
        <a href="{{ url('/#bulk-order') }}" class="bk-card bk-stat">
            <span class="bk-icon-tile"><x-heroicon-o-arrow-path /></span>
            <span><span class="block font-bold">Order again</span><span class="bk-muted block text-sm">Repeat a past order</span></span>
        </a>
    </div>

    <section class="bk-card mt-5 p-5">
        <div class="bk-section-head !mb-2">
            <div><h2 class="!text-lg">Recent orders</h2></div>
            @if($orders->isNotEmpty())<a href="{{ route('account.orders') }}" class="bk-link text-sm">All orders →</a>@endif
        </div>
        @include('account.partials.order-table', ['orders' => $orders])
    </section>

    <div class="mt-5 grid gap-5 sm:grid-cols-2">
        <section class="bk-card p-5 text-sm">
            <div class="flex items-center justify-between"><h2 class="font-extrabold">Default address</h2><a href="{{ route('account.addresses') }}" class="bk-link">Edit</a></div>
            @if($address)
                <div class="bk-muted mt-3 leading-relaxed">@foreach($address->lines() as $line)<p>{{ $line }}</p>@endforeach</div>
            @else
                <p class="bk-muted mt-3">No address saved yet. <a href="{{ route('account.addresses') }}" class="bk-link">Add one</a> to check out faster.</p>
            @endif
        </section>
        <section class="bk-card p-5 text-sm">
            <div class="flex items-center justify-between"><h2 class="font-extrabold">Account details</h2><a href="{{ route('account.profile') }}" class="bk-link">Edit</a></div>
            <div class="bk-muted mt-3 leading-relaxed"><p>{{ $user->name }}</p><p>{{ $user->email }}</p>@if($user->phone)<p>{{ $user->phone }}</p>@endif</div>
        </section>
    </div>
</x-account-shell>
@endsection
