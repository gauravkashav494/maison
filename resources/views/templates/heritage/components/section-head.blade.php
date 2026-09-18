@props(['eyebrow' => null, 'title', 'text' => null, 'href' => null, 'label' => 'View all', 'center' => false])
<div {{ $attributes->class(['mb-8 flex flex-col gap-4 lg:mb-10', 'items-center text-center' => $center, 'sm:flex-row sm:items-end sm:justify-between' => ! $center]) }}>
    <div class="max-w-2xl">
        @if($eyebrow)<p class="eyebrow {{ $center ? 'eyebrow-center' : '' }}">{{ $eyebrow }}</p>@endif
        <h2 class="section-title mt-2">{{ $title }}</h2>
        @if($text)<p class="lead mt-2">{{ $text }}</p>@endif
    </div>
    @if($href)<a href="{{ $href }}" class="section-link shrink-0">{{ $label }} <x-ico name="arrow-right" :size="16" /></a>@endif
</div>
