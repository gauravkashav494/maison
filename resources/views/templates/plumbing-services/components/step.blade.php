{{-- Numbered "how it works" step --}}
@props(['n', 'title', 'text' => null, 'icon' => null])
<div {{ $attributes->class('relative flex gap-4 lg:flex-col lg:gap-0') }}>
    <div class="flex flex-col items-center lg:mb-4 lg:flex-row lg:gap-3">
        <span class="step-num">{{ str_pad($n, 2, '0', STR_PAD_LEFT) }}</span>
        @if($icon)<span class="trust-ico hidden lg:grid"><x-ico :name="$icon" :size="20" /></span>@endif
    </div>
    <div class="min-w-0 pb-6 lg:pb-0">
        <h3 class="font-display text-base font-extrabold">{{ $title }}</h3>
        @if($text)<p class="mt-1 text-sm leading-relaxed text-slate">{{ $text }}</p>@endif
    </div>
</div>