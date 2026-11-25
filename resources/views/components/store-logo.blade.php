{{--
    The store's uploaded logo (Site settings → Brand) in place of a template's text wordmark.
    Without an upload, the wordmark passed as the slot is shown exactly as before.

    size:   height classes for this spot, e.g. "h-9 lg:h-11"
    max:    width cap, so a very wide logo cannot push the header apart
    onDark: on a dark or coloured background, the logo sits on a small white plate so a dark
            logo stays visible
--}}
@props(['size' => 'h-10', 'max' => 'max-w-[11rem]', 'onDark' => false])
@php($logoUrl = store_logo_url())
@if($logoUrl)
    <img src="{{ $logoUrl }}" alt="{{ setting('site.name', config('app.name')) }}"
         {{ $attributes->class([$size, $max, 'block w-auto object-contain', 'rounded-lg bg-white p-1.5' => $onDark]) }}>
@else
    {{ $slot }}
@endif
