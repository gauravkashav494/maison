@php
    // Story banner: rounded 1160px slider with copy over the image; a slide may carry a video (YouTube or MP4) that plays in place.
    $hs = tsetting('site');
    $slides = collect($g['story_slides'] ?? [])->filter(fn ($s) => ! empty($s['image']))->values();
    if ($slides->isEmpty() && ! empty($g['video_poster'])) {
        // Older settings: a single poster + optional video
        $slides = collect([['image' => $g['video_poster'], 'video_url' => $g['video_url'] ?? null]]);
    }
    $yt = fn (?string $url) => $url && preg_match('/(?:v=|youtu\.be\/|embed\/)([\w-]{6,})/', $url, $m) ? "https://www.youtube.com/embed/{$m[1]}?autoplay=1&rel=0" : null;
@endphp
@if($slides->isNotEmpty())
<section class="bg-warm pb-6 pt-10 lg:pb-0 lg:pt-[60px]" x-data="{ ...heroCarousel({{ $slides->count() }}, 7000), playing: null }" x-init="$watch('index', () => playing = null); $watch('playing', (v) => paused = v !== null)" @mouseenter="paused = true" @mouseleave="paused = playing !== null" aria-roledescription="carousel">
    <div class="h-container">
        <div class="banner-round relative mx-auto aspect-[4/3] w-full max-w-[1160px] bg-maroon-deep sm:aspect-[16/9] lg:aspect-[21/9]">
            @foreach($slides as $i => $s)
                @php $video = $s['video_url'] ?? null; $embed = $yt($video); $href = $s['cta_url'] ?? null; @endphp
                <div x-show="index === {{ $i }}" x-transition:enter="transition duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="absolute inset-0" @if($i > 0) x-cloak @endif>
                    {{-- Poster + copy --}}
                    <div x-show="playing !== {{ $i }}" class="absolute inset-0">
                        <img src="{{ \App\Support\Media::url($s['image']) }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                        <span class="absolute inset-0 bg-gradient-to-r from-maroon-deep/80 via-maroon-deep/35 to-maroon-deep/5"></span>
                        <span class="absolute inset-x-0 bottom-0 h-3/4 bg-gradient-to-t from-maroon-deep/85 via-maroon-deep/30 to-transparent"></span>
                        <span class="absolute left-5 top-5 hidden h-14 w-14 place-items-center rounded-full border-2 border-gold bg-cream text-red sm:grid lg:left-8 lg:top-8 lg:h-[4.5rem] lg:w-[4.5rem]"><span class="flex flex-col items-center leading-none"><x-ico name="diamond" :size="14" :stroke="1.8" /><span class="mt-1 font-serif text-[0.55rem] font-semibold uppercase tracking-[0.1em]">{{ Str::substr($hs['logo_primary'] ?? 'Annapurna', 0, 9) }}</span><span class="text-[0.45rem] uppercase tracking-[0.2em] text-gold">{{ $hs['logo_sub'] ?? '' }}</span></span></span>

                        <div class="absolute inset-x-0 bottom-0 p-6 text-cream sm:p-10 lg:px-14 lg:pb-10">
                            <div class="max-w-xl">
                                @if(!empty($s['eyebrow']))<p class="eyebrow text-gold-light">{{ $s['eyebrow'] }}</p>@endif
                                @if(!empty($s['heading']))<h2 class="display mt-2 text-2xl leading-tight text-cream sm:text-3xl lg:text-[2.25rem]">{{ $s['heading'] }}</h2>@endif
                                @if(!empty($s['text']))<p class="mt-3 max-w-lg text-sm leading-relaxed text-cream/85 sm:text-base">{{ $s['text'] }}</p>@endif
                                <div class="mt-5 flex flex-wrap items-center gap-3">
                                    @if(!empty($s['cta_label']) && $href)<a href="{{ $href }}" class="btn btn-gold rounded-full px-6">{{ $s['cta_label'] }} <x-ico name="arrow-right" :size="16" /></a>@endif
                                    @if($video)<button type="button" @click="playing = {{ $i }}" class="inline-flex items-center gap-2 rounded-full border border-cream/60 bg-white/10 px-5 py-2.5 text-sm font-semibold text-cream backdrop-blur hover:bg-white/20"><span class="grid h-7 w-7 place-items-center rounded-full bg-white text-red"><x-ico name="play" :size="12" /></span> {{ $s['video_label'] ?? 'Watch the film' }}</button>@endif
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Video, once played --}}
                    @if($video)
                        <template x-if="playing === {{ $i }}">
                            <div class="absolute inset-0 bg-black">
                                @if($embed)<iframe src="{{ $embed }}" class="h-full w-full" allow="autoplay; encrypted-media" allowfullscreen title="{{ $s['heading'] ?? 'Brand video' }}"></iframe>@else<video src="{{ \App\Support\Media::url($video) }}" controls autoplay playsinline class="h-full w-full object-cover"></video>@endif
                                <button type="button" @click="playing = null" class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-black/60 text-white hover:bg-black/80" aria-label="Close video"><x-ico name="close" :size="16" /></button>
                            </div>
                        </template>
                    @endif
                </div>
            @endforeach

            @if($slides->count() > 1)
                <button type="button" @click="prev()" class="rail-btn absolute left-3 top-1/2 hidden -translate-y-1/2 lg:grid" aria-label="Previous"><x-ico name="chevron-left" :size="18" /></button>
                <button type="button" @click="next()" class="rail-btn absolute right-3 top-1/2 hidden -translate-y-1/2 lg:grid" aria-label="Next"><x-ico name="chevron-right" :size="18" /></button>
            @endif
        </div>
    </div>
    @if($slides->count() > 1)
        <div class="mt-5 flex h-[29px] items-center justify-center gap-2">
            @foreach($slides as $i => $s)<button type="button" @click="go({{ $i }})" class="h-2 rounded-full transition-all" :class="index === {{ $i }} ? 'w-6 bg-red' : 'w-2 bg-gold/50 hover:bg-gold'" aria-label="Go to slide {{ $i + 1 }}"></button>@endforeach
        </div>
    @else
        <div class="h-5 lg:h-[29px]"></div>
    @endif
</section>
@endif
