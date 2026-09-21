{{-- Before/after card with a draggable comparison slider --}}
@props(['project', 'compact' => false])
<article {{ $attributes->class(['card flex flex-col overflow-hidden', 'w-[18rem]' => $compact]) }} x-data="beforeAfter()">
    <div class="p-2 pb-0">
        <div class="ba" :style="`--pos:${pos}%`">
            @if($project->before_url)<img src="{{ $project->before_url }}" alt="Before — {{ $project->title }}" loading="lazy" decoding="async" class="ba-before">@endif
            @if($project->after_url)<img src="{{ $project->after_url }}" alt="After — {{ $project->title }}" loading="lazy" decoding="async" class="ba-after">@endif
            <span class="ba-tag left-2.5">Before</span><span class="ba-tag right-2.5">After</span>
            <div class="ba-handle"></div>
            <input type="range" min="0" max="100" x-model="pos" aria-label="Compare before and after">
        </div>
    </div>
    <div class="p-4">
        <h3 class="font-display text-[0.95rem] font-extrabold leading-snug">{{ $project->title }}</h3>
        <p class="mt-0.5 text-xs font-semibold text-primary">{{ $project->service?->name }}@if($project->service && $project->location) · @endif<span class="text-slate">{{ $project->location }}</span></p>
        @if($project->description)<p class="mt-2 text-xs leading-relaxed text-slate {{ $compact ? 'line-clamp-2' : '' }}">{{ $project->description }}</p>@endif
    </div>
</article>