@php $banners = array_values(array_filter($g['hero_banners'] ?? [], fn ($b) => ! empty($b['heading']))); @endphp
@if($banners)
<section class="g-container pt-4 lg:pt-6" x-data="heroCarousel({{ count($banners) }})" @mouseenter="paused = true" @mouseleave="paused = false" aria-roledescription="carousel">
    <div class="relative overflow-hidden rounded-2xl">
        @foreach($banners as $i => $b)
            <div x-show="index === {{ $i }}" x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="hero-theme-{{ $b['theme'] ?? 'green' }} relative grid min-h-[15rem] items-center text-white sm:min-h-[19rem] lg:min-h-[22rem] lg:grid-cols-2" @if($i > 0) x-cloak @endif>
                <div class="relative z-10 p-6 sm:p-8 lg:p-12">
                    @if(!empty($b['eyebrow']))<p class="inline-block rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wider backdrop-blur">{{ $b['eyebrow'] }}</p>@endif
                    <h2 class="mt-3 max-w-md text-2xl font-extrabold leading-tight sm:text-3xl lg:text-[2.5rem]">{{ $b['heading'] }}</h2>
                    @if(!empty($b['text']))<p class="mt-2 max-w-md text-sm text-white/85 sm:text-base">{{ $b['text'] }}</p>@endif
                    @if(!empty($b['cta_label']))<a href="{{ $b['cta_url'] ?? '#' }}" class="btn mt-5 bg-white text-ink hover:bg-paper">{{ $b['cta_label'] }} <x-ico name="arrow-right" :size="16" /></a>@endif
                </div>
                @if(!empty($b['image']))
                    <div class="absolute inset-0 lg:relative lg:h-full">
                        <img src="{{ \App\Support\Media::url($b['image']) }}" alt="" class="img-cover opacity-40 lg:opacity-100" @if($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                        <div class="absolute inset-0 hidden lg:block" style="background: linear-gradient(90deg, rgba(0,0,0,.35), transparent 40%)"></div>
                    </div>
                @endif
            </div>
        @endforeach
        @if(count($banners) > 1)
            {{-- Arrows sit together in the bottom-right corner so they never overlap the headline --}}
            <div class="absolute bottom-4 right-4 hidden gap-2 lg:flex">
                <button type="button" @click="prev()" class="rail-btn" aria-label="Previous slide"><x-ico name="chevron-left" :size="18" /></button>
                <button type="button" @click="next()" class="rail-btn" aria-label="Next slide"><x-ico name="chevron-right" :size="18" /></button>
            </div>
            <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
                @foreach($banners as $i => $b)<button type="button" @click="go({{ $i }})" class="h-1.5 rounded-full bg-white/60 transition-all" :class="index === {{ $i }} ? 'w-6 bg-white' : 'w-1.5'" aria-label="Go to slide {{ $i + 1 }}"></button>@endforeach
            </div>
        @endif
    </div>
</section>
@endif
