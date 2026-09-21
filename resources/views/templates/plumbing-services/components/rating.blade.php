@props(['value' => 5, 'size' => 14, 'label' => null])
<span {{ $attributes->class('inline-flex items-center gap-1.5') }} aria-label="Rated {{ $value }} out of 5">
    <span class="rating-stars">
        @for($i = 1; $i <= 5; $i++)
            <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="{{ $i <= round($value) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.8 5.8 6.4.9-4.6 4.5 1.1 6.3L12 17.5l-5.7 3 1.1-6.3L2.8 9.7l6.4-.9L12 3z"/></svg>
        @endfor
    </span>
    @if($label)<span class="text-xs font-semibold text-slate">{{ $label }}</span>@endif
</span>