@props(['review', 'compact' => false])
<article {{ $attributes->class(['card flex flex-col p-4 lg:p-5', 'w-[17rem]' => $compact]) }}>
    <div class="flex items-center justify-between">
        <x-rating :value="$review->rating" />
        <x-ico name="quote" :size="20" class="text-sky-dark" />
    </div>
    <p class="mt-3 flex-1 text-sm leading-relaxed text-slate {{ $compact ? 'line-clamp-3' : '' }}">{{ $review->body }}</p>
    <div class="mt-4 flex items-center gap-3 border-t border-line-soft pt-3">
        @if($review->image_url)<img src="{{ $review->image_url }}" alt="" loading="lazy" class="h-9 w-9 rounded-full object-cover">
        @else<span class="grid h-9 w-9 place-items-center rounded-full bg-sky font-display text-sm font-extrabold text-primary">{{ Str::of($review->name)->substr(0, 1) }}</span>@endif
        <div class="min-w-0">
            <p class="truncate text-sm font-bold">{{ $review->name }}</p>
            <p class="truncate text-xs text-slate">{{ $review->service?->name }}@if($review->service && $review->location) · @endif{{ $review->location }}</p>
        </div>
    </div>
</article>