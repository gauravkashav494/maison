@props(['value', 'count' => null, 'size' => 10])
<div {{ $attributes->class('flex items-center gap-1.5') }} aria-label="{{ $value }} out of 5 stars">
    <div class="flex gap-0.5">
        @for($i = 0; $i < 5; $i++)
            @php $fill = max(0, min(1, $value - $i)); @endphp
            <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M10 1.5l2.6 5.6 6.1.7-4.5 4.2 1.2 6-5.4-3-5.4 3 1.2-6L1.3 7.8l6.1-.7z" fill="currentColor" opacity="{{ $fill >= 0.5 ? 1 : 0.2 }}"/>
            </svg>
        @endfor
    </div>
    @if($count !== null)<span class="text-[0.6875rem] text-smoke tabular-nums">({{ $count }})</span>@endif
</div>
