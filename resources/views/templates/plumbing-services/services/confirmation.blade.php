@extends('layouts.app', ['appBar' => ['title' => 'Request received', 'back' => false]])

@section('content')
@php $biz = template()->contact(); $isQuote = $booking->type === 'quote'; @endphp
<section class="ps-container py-6 lg:py-14">
    <div class="mx-auto max-w-2xl">
        <div class="card overflow-hidden">
            <div class="band-deep px-6 py-8 text-center">
                <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-white text-success"><x-ico name="check" :size="32" :stroke="2.5" /></span>
                <h1 class="mt-4 font-display text-2xl font-extrabold lg:text-3xl">{{ $isQuote ? 'Quote request received' : ($booking->type === 'emergency' ? 'Emergency request received' : 'Booking request received') }}</h1>
                <p class="mt-2 text-sm text-white/85">Thank you, {{ Str::before($booking->name, ' ') }}! {{ $isQuote ? 'We will study your requirement and call you with the details and expected cost.' : ($booking->type === 'emergency' ? 'Our dispatcher is calling you now to confirm the address and send the nearest plumber.' : 'We have received your plumbing service request and will call to confirm the slot.') }}</p>
                <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-sm font-bold ring-1 ring-white/30">Reference <span class="font-mono tracking-wider">{{ $booking->reference }}</span></p>
            </div>
            <dl class="divide-y divide-line-soft">
                <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Service</dt><dd class="text-right text-sm font-bold">{{ $booking->service_name }}</dd></div>
                @if($booking->problem)<div class="flex justify-between gap-4 px-5 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Problem</dt><dd class="max-w-[60%] text-right text-sm">{{ $booking->problem }}</dd></div>@endif
                @if($booking->address || $booking->area)<div class="flex justify-between gap-4 px-5 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Location</dt><dd class="max-w-[60%] text-right text-sm">{{ implode(', ', array_filter([$booking->address, $booking->area])) }}</dd></div>@endif
                @if(!$isQuote)
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Date</dt><dd class="text-sm font-bold">{{ $booking->preferred_date?->isToday() ? 'Today' : ($booking->preferred_date?->isTomorrow() ? 'Tomorrow' : $booking->preferred_date?->format('D, d M Y')) }}</dd></div>
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Time</dt><dd class="text-sm font-bold">{{ $booking->slot_label }}</dd></div>
                @endif
                <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Contact</dt><dd class="text-right text-sm">{{ $booking->name }}<br>{{ $booking->phone }}@if($booking->email)<br>{{ $booking->email }}@endif</dd></div>
                <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-xs font-bold uppercase tracking-wider text-slate">Status</dt><dd><span class="pill pill-accent">{{ $booking->status_label }}</span></dd></div>
            </dl>
            <div class="grid gap-2 border-t border-line-soft p-4 sm:grid-cols-3">
                <a href="{{ $biz['phone_href'] }}" class="btn btn-outline"><x-ico name="phone" :size="16" /> Call support</a>
                @if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-ico name="whatsapp" :size="16" /> WhatsApp us</a>@endif
                <a href="{{ route('home') }}" class="btn btn-primary">Back to home</a>
            </div>
        </div>
        <p class="mt-4 text-center text-xs text-slate">Save your reference to check the status any time under <a href="{{ route('bookings') }}" class="font-semibold text-primary">My bookings</a>.</p>
    </div>
</section>
@endsection