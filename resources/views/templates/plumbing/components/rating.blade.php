@props(['value' => 0, 'count' => null, 'size' => 14])
<span {{ $attributes->class('inline-flex items-center gap-1') }} aria-label="Rated {{ $value }} out of 5">
    <span class="flex text-star">
        @for($i = 1; $i <= 5; $i++)
            <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="{{ $i <= round($value) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.8 5.8 6.4.9-4.6 4.5 1.1 6.3L12 17.5l-5.7 3 1.1-6.3L2.8 9.7l6.4-.9L12 3z"/></svg>
        @endfor
    </span>
    @if($count !== null)<span class="text-xs text-slate">{{ number_format($value, 1) }} ({{ number_format($count) }})</span>@endif
</span>
