@props(['title', 'text' => null, 'href' => null, 'label' => 'See all'])
<div {{ $attributes->class('mb-4 flex items-end justify-between gap-4') }}>
    <div class="min-w-0">
        <h2 class="section-title">{{ $title }}</h2>
        @if($text)<p class="mt-0.5 text-sm text-slate">{{ $text }}</p>@endif
    </div>
    @if($href)<a href="{{ $href }}" class="section-link shrink-0">{{ $label }} <x-ico name="chevron-right" :size="16" /></a>@endif
</div>
