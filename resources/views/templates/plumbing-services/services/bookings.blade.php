@extends('layouts.app', ['appBar' => ['title' => 'My bookings', 'back' => false]])

@section('content')
@php $biz = template()->contact(); @endphp
<x-page-head title="My bookings" text="Check the status of a booking or quote request." :breadcrumbs="['My bookings' => null]" />
<section class="ps-container grid gap-6 py-5 lg:grid-cols-12 lg:gap-10 lg:py-10">
    <div class="lg:col-span-5">
        <form method="post" action="{{ route('bookings') }}" class="card space-y-3 p-4 sm:p-6">
            @csrf
            <p class="font-display text-base font-extrabold">Find a request</p>
            <div><label class="label" for="reference">Reference number</label><input id="reference" name="reference" required value="{{ old('reference') }}" placeholder="PS-260922-AB12" class="field font-mono uppercase @error('reference') field-error @enderror">@error('reference')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="phone">Mobile number used</label><input id="phone" name="phone" type="tel" inputmode="tel" required value="{{ old('phone') }}" class="field @error('phone') field-error @enderror">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
            @if(session('lookup_error'))<p class="error">{{ session('lookup_error') }}</p>@endif
            <button type="submit" class="btn btn-primary btn-block">Check status</button>
            @guest<p class="text-center text-xs text-slate"><a href="{{ route('login') }}" class="font-semibold text-primary">Log in</a> to see all your bookings in one place.</p>@endguest
        </form>
        <div class="card mt-4 p-4 sm:p-5">
            <p class="text-sm font-bold">Need to change something?</p>
            <p class="mt-1 text-xs text-slate">Call or WhatsApp us with your reference — rescheduling is free before the plumber is dispatched.</p>
            <div class="mt-3 flex gap-2"><a href="{{ $biz['phone_href'] }}" class="btn btn-outline btn-sm"><x-ico name="phone" :size="14" /> Call</a>@if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm"><x-ico name="whatsapp" :size="14" /> WhatsApp</a>@endif</div>
        </div>
    </div>
    <div class="space-y-3 lg:col-span-7">
        @if($found)
            <p class="text-xs font-bold uppercase tracking-wider text-slate">Found</p>
            <x-booking-row :booking="$found" />
        @endif
        @if($bookings->isNotEmpty())
            <p class="pt-2 text-xs font-bold uppercase tracking-wider text-slate">Your requests</p>
            @foreach($bookings as $b)<x-booking-row :booking="$b" />@endforeach
        @elseif(!$found)
            <x-empty-state icon="calendar" title="No bookings yet" text="Book a plumber online and your requests will show up here.">
                <a href="{{ route('booking.create') }}" class="btn btn-primary">Book a plumber</a>
            </x-empty-state>
        @endif
    </div>
</section>
@endsection