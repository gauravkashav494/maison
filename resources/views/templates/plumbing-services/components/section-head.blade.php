{{-- Section heading with optional sub-heading and "See all" link --}}
@props(['title', 'sub' => null, 'link' => null, 'linkLabel' => 'See all', 'eyebrow' => null, 'center' => false])
<div {{ $attributes->class(['sec-head', 'justify-center text-center' => $center]) }}>
    <div class="min-w-0">
        @if($eyebrow)<p class="eyebrow mb-2">{{ $eyebrow }}</p>@endif
        <h2 class="sec-title text-balance">{{ $title }}</h2>
        @if($sub)<p class="sec-sub {{ $center ? 'mx-auto' : '' }}">{{ $sub }}</p>@endif
    </div>
    @if($link)<a href="{{ $link }}" class="sec-link">{{ $linkLabel }} <x-ico name="chevron-right" :size="14" /></a>@endif
</div>