{{-- One service request in a list (account, lookup) --}}
@props(['booking'])
@php $tone = match($booking->status) { 'completed' => 'pill-ok', 'cancelled' => 'pill-danger', 'scheduled' => 'pill-sky', default => 'pill-accent' }; @endphp
<a href="{{ $booking->url }}" {{ $attributes->class('card card-hover flex items-center gap-3 p-4') }}>
    <span class="svc-ico h-11 w-11 {{ $booking->type === 'emergency' ? 'svc-ico-danger' : '' }}"><x-ico :name="$booking->service?->icon ?: ($booking->type === 'quote' ? 'file' : 'wrench')" :size="22" /></span>
    <span class="min-w-0 flex-1">
        <span class="flex items-center gap-2"><span class="truncate font-display text-sm font-extrabold">{{ $booking->service_name ?: $booking->type_label }}</span><span class="pill {{ $tone }}">{{ $booking->status_label }}</span></span>
        <span class="mt-0.5 block truncate text-xs text-slate">{{ $booking->reference }} · {{ $booking->type_label }}@if($booking->preferred_date) · {{ $booking->preferred_date->format('D, d M') }}@endif @if($booking->time_slot && $booking->time_slot !== 'asap')· {{ Str::before($booking->slot_label, ' (') }}@endif</span>
    </span>
    <x-ico name="chevron-right" :size="18" class="text-mist" />
</a>