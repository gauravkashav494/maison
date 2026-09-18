@php $slides = array_values(array_filter($g['hero_slides'] ?? [], fn ($s) => ! empty($s['heading']))); @endphp
@if($slides)
<section class="relative bg-maroon-deep text-cream" x-data="heroCarousel({{ count($slides) }})" @mouseenter="paused = true" @mouseleave="paused = false" aria-roledescription="carousel">
    <div class="relative min-h-[32rem] overflow-hidden sm:min-h-[36rem] lg:min-h-[40rem]">
        @foreach($slides as $i => $s)
            <div x-show="index === {{ $i }}" x-transition:enter="transition duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition duration-500" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0" @if($i > 0) x-cloak @endif>
                @if(!empty($s['image']))<img src="{{ \App\Support\Media::url($s['image']) }}" alt="" class="absolute inset-0 h-full w-full object-cover" :class="index === {{ $i }} && 'animate-ken'" @if($i === 0) fetchpriority="high" @else loading="lazy" @endif>@endif
                <div class="absolute inset-0 bg-gradient-to-r from-maroon-deep/90 via-maroon-deep/55 to-maroon-deep/10"></div>
                <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-maroon-deep/70 to-transparent"></div>
                <div class="h-container relative flex h-full min-h-[32rem] items-center sm:min-h-[36rem] lg:min-h-[40rem]">
                    <div class="max-w-xl py-16 {{ ($s['align'] ?? 'left') === 'center' ? 'mx-auto text-center' : '' }}">
                        @if(!empty($s['eyebrow']))<p class="eyebrow text-gold-light {{ ($s['align'] ?? 'left') === 'center' ? 'eyebrow-center' : '' }}" x-show="index === {{ $i }}" x-transition:enter="transition duration-700 delay-100" x-transition:enter-start="opacity-0 translate-y-3">{{ $s['eyebrow'] }}</p>@endif
                        <h1 class="display mt-4 text-4xl text-cream sm:text-5xl lg:text-[3.75rem]" x-show="index === {{ $i }}" x-transition:enter="transition duration-700 delay-200" x-transition:enter-start="opacity-0 translate-y-4">{{ $s['heading'] }}</h1>
                        @if(!empty($s['text']))<p class="mt-5 max-w-lg text-base text-cream/85 sm:text-lg" x-show="index === {{ $i }}" x-transition:enter="transition duration-700 delay-300" x-transition:enter-start="opacity-0 translate-y-4">{{ $s['text'] }}</p>@endif
                        <div class="mt-8 flex flex-wrap items-center gap-3 {{ ($s['align'] ?? 'left') === 'center' ? 'justify-center' : '' }}" x-show="index === {{ $i }}" x-transition:enter="transition duration-700 delay-[400ms]" x-transition:enter-start="opacity-0 translate-y-4">
                            @if(!empty($s['cta_label']))<a href="{{ $s['cta_url'] ?? '#' }}" class="btn btn-gold btn-lg">{{ $s['cta_label'] }} <x-ico name="arrow-right" :size="16" /></a>@endif
                            @if(!empty($s['secondary_label']))<a href="{{ $s['secondary_url'] ?? '#' }}" class="btn btn-lg border-cream/40 text-cream hover:border-gold hover:text-gold-light">{{ $s['secondary_label'] }}</a>@endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        @if(count($slides) > 1)
            <div class="h-container absolute inset-x-0 bottom-6 flex items-center justify-between">
                <div class="flex gap-2">@foreach($slides as $i => $s)<button type="button" @click="go({{ $i }})" class="h-1 rounded-full bg-cream/50 transition-all" :class="index === {{ $i }} ? 'w-10 bg-gold' : 'w-4'" aria-label="Go to slide {{ $i + 1 }}"></button>@endforeach</div>
                <div class="hidden gap-2 sm:flex">
                    <button type="button" @click="prev()" class="grid h-10 w-10 place-items-center rounded-full border border-cream/30 text-cream hover:border-gold hover:text-gold-light" aria-label="Previous slide"><x-ico name="chevron-left" :size="18" /></button>
                    <button type="button" @click="next()" class="grid h-10 w-10 place-items-center rounded-full border border-cream/30 text-cream hover:border-gold hover:text-gold-light" aria-label="Next slide"><x-ico name="chevron-right" :size="18" /></button>
                </div>
            </div>
        @endif
    </div>
    <div class="gold-rule"></div>
</section>
@endif
