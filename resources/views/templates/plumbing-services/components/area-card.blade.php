@props(['area', 'compact' => false])
<a href="{{ $area->url }}" {{ $attributes->class(['card card-hover relative flex overflow-hidden', 'w-[11rem] flex-col' => $compact, 'items-center gap-3 p-3' => ! $compact]) }}>
    <span class="{{ $compact ? 'aspect-[16/9] w-full' : 'h-16 w-20 shrink-0 rounded-xl' }} overflow-hidden bg-sky">@if($area->image_url)<img src="{{ $area->image_url }}" alt="" loading="lazy" decoding="async" class="img-cover">@endif</span>
    <span class="min-w-0 flex-1 {{ $compact ? 'p-3' : '' }}">
        <span class="flex items-center gap-1 font-display text-sm font-extrabold"><x-ico name="map-pin" :size="14" class="text-primary" /> {{ $area->name }}</span>
        @if($area->state)<span class="block text-xs text-slate">{{ $area->state }}</span>@endif
        @if($area->response_time)<span class="pill pill-ok mt-1.5"><x-ico name="bolt" :size="11" /> {{ $area->response_time }}</span>@endif
    </span>
    @unless($compact)<x-ico name="chevron-right" :size="18" class="text-mist" />@endunless
</a>