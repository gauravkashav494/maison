@extends('layouts.app', ['appBar' => ['title' => 'My account', 'back' => false]])

@section('content')
@php $requests = \App\Models\ServiceRequest::with('service')->where('user_id', $user->id)->orderByDesc('id')->limit(5)->get(); @endphp
<x-account-shell title="Hi, {{ Str::of($user->name)->before(' ') }}">
    <div class="grid gap-3 sm:grid-cols-3">
        <a href="{{ route('account.bookings') }}" class="card card-hover p-4"><x-ico name="calendar" :size="22" class="text-primary" /><p class="mt-2 font-display text-2xl font-extrabold">{{ \App\Models\ServiceRequest::where('user_id', $user->id)->count() }}</p><p class="text-xs text-slate">Service requests</p></a>
        <a href="{{ route('account.addresses') }}" class="card card-hover p-4"><x-ico name="map-pin" :size="22" class="text-accent-dark" /><p class="mt-2 font-display text-2xl font-extrabold">{{ $user->addresses()->count() }}</p><p class="text-xs text-slate">Saved addresses</p></a>
        <a href="{{ route('booking.create') }}" class="band-deep card card-hover flex flex-col justify-between p-4"><x-ico name="wrench" :size="22" class="text-accent" /><p class="mt-2 font-display text-base font-extrabold">Book a plumber</p><p class="text-xs text-white/80">Same-day slots available</p></a>
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="card p-4 sm:p-5">
            <div class="flex items-center justify-between"><p class="font-display text-sm font-extrabold">Recent requests</p><a href="{{ route('account.bookings') }}" class="sec-link text-xs">View all</a></div>
            @if($requests->isEmpty())
                <p class="mt-3 text-sm text-slate">No requests yet. <a href="{{ route('booking.create') }}" class="font-semibold text-primary">Book a plumber</a></p>
            @else
                <div class="mt-3 space-y-2">@foreach($requests as $b)<x-booking-row :booking="$b" class="!p-3" />@endforeach</div>
            @endif
        </div>
        <div class="space-y-4">
            <div class="card p-4 sm:p-5">
                <div class="flex items-center justify-between"><p class="font-display text-sm font-extrabold">Default address</p><a href="{{ route('account.addresses') }}" class="sec-link text-xs">Manage</a></div>
                @if($address)<div class="mt-2 text-sm leading-relaxed text-slate">@foreach($address->lines() as $l)<p>{{ $l }}</p>@endforeach</div>@else<p class="mt-2 text-sm text-slate">No address saved yet.</p>@endif
            </div>
            <div class="card p-4 sm:p-5">
                <div class="flex items-center justify-between"><p class="font-display text-sm font-extrabold">Profile</p><a href="{{ route('account.profile') }}" class="sec-link text-xs">Edit</a></div>
                <p class="mt-2 text-sm">{{ $user->name }}</p><p class="text-sm text-slate">{{ $user->email }}@if($user->phone) · {{ $user->phone }}@endif</p>
            </div>
        </div>
    </div>
</x-account-shell>
@endsection