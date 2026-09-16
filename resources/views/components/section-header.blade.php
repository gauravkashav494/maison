@props(['eyebrow' => null, 'title', 'description' => null, 'cta' => null, 'ctaUrl' => null, 'align' => 'split', 'light' => false])
@php
    $tone = $light ? 'text-ivory' : 'text-ink';
    $muted = $light ? 'text-ivory/60' : 'text-smoke';
@endphp
<div {{ $attributes->class(['reveal flex gap-6', 'flex-col items-center text-center' => $align === 'center', 'flex-col items-start' => $align === 'left', 'flex-col md:flex-row md:items-end md:justify-between' => $align === 'split']) }} x-data x-intersect.once="$el.classList.add('is-visible')">
    <div class="max-w-2xl {{ $align === 'center' ? 'mx-auto' : '' }}">
        @if($eyebrow)<p class="eyebrow mb-4 {{ $muted }}">{{ $eyebrow }}</p>@endif
        <h2 class="display-md text-balance {{ $tone }}">{!! emph($title) !!}</h2>
        @if($description)<p class="mt-4 max-w-md text-[0.9375rem] leading-relaxed text-pretty {{ $muted }}">{{ $description }}</p>@endif
    </div>
    @if($cta && $ctaUrl)
        <a href="{{ $ctaUrl }}" class="link-underline eyebrow inline-flex shrink-0 items-center gap-2 pb-0.5 {{ $tone }}">{{ $cta }} <span aria-hidden="true">→</span></a>
    @endif
</div>
