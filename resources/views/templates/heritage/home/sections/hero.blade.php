@php $slides = array_values(array_filter($g['hero_slides'] ?? [], fn ($s) => ! empty($s['heading']))); @endphp
@if($slides)
{{-- Full-bleed slider: edge to edge, 3:1 on desktop with 18px corners. The copy is aligned to the page container. --}}
<section class="relative" x-data="heroCarousel({{ count($slides) }})" @mouseenter="paused = true" @mouseleave="paused = false" aria-roledescription="carousel">
    <div class="relative overflow-hidden rounded-[18px] bg-cream-dark">
        @foreach($slides as $i => $s)
            @php $href = $s['cta_url'] ?? null; @endphp
            <div x-show="index === {{ $i }}" x-transition:enter="transition duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="relative flex min-h-[22rem] items-center sm:aspect-[2/1] sm:min-h-0 lg:aspect-[3/1]" @if($i > 0) x-cloak @endif>
                {{-- Photo with a soft cream wash on the left so the copy stays legible --}}
                @if(!empty($s['image']))<img src="{{ \App\Support\Media::url($s['image']) }}" alt="" class="absolute inset-0 h-full w-full object-cover object-right" @if($i === 0) fetchpriority="high" @else loading="lazy" @endif>@endif
                <div class="absolute inset-0 bg-gradient-to-r from-cream via-cream/85 to-transparent sm:via-cream/70 sm:to-cream/0"></div>

                <div class="h-container relative z-10">
                    <div class="flex max-w-xl flex-col justify-center py-8 pb-12 lg:max-w-3xl lg:py-10">
                        @if(!empty($s['eyebrow']))<span class="inline-flex w-max items-center gap-2 rounded-full border-2 border-gold bg-cream px-3 py-1 font-serif text-[0.7rem] font-semibold uppercase tracking-[0.15em] text-red"><x-ico name="diamond" :size="11" /> {{ $s['eyebrow'] }}</span>@endif
                        <h1 class="display mt-4 text-[2rem] leading-[1.05] text-red sm:text-5xl lg:text-[3.25rem]">{{ $s['heading'] }}</h1>
                        @if(!empty($s['text']))<p class="mt-5 inline-block max-w-md rounded-lg bg-red px-4 py-3 text-sm leading-snug text-cream sm:text-base">{{ $s['text'] }}</p>@endif
                        @if(!empty($s['badges']))
                            <ul class="mt-6 flex flex-wrap items-center gap-y-3 lg:flex-nowrap">
                                @foreach(array_slice((array) $s['badges'], 0, 4) as $b)<li class="flex items-center gap-2 pr-4 text-[0.75rem] font-semibold leading-tight text-ink sm:border-r sm:border-ink/30 sm:last:border-0 sm:pr-4 sm:mr-4 sm:last:mr-0 sm:last:pr-0"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border-2 border-gold bg-white text-red"><x-ico name="leaf" :size="15" /></span><span class="max-w-[6.5rem]">{{ $b }}</span></li>@endforeach
                            </ul>
                        @endif
                        {{-- A slide with a link always gets a visible button (label defaults to "Shop now") --}}
                        @if($href || !empty($s['cta_label']))<div class="mt-6"><a href="{{ $href ?? '#' }}" class="btn btn-primary rounded-full px-7">{{ $s['cta_label'] ?: 'Shop now' }} <x-ico name="arrow-right" :size="16" /></a></div>@endif
                    </div>
                </div>
            </div>
        @endforeach
        @if(count($slides) > 1)
            <div class="absolute bottom-3 left-1/2 z-10 flex -translate-x-1/2 gap-1.5">@foreach($slides as $i => $s)<button type="button" @click="go({{ $i }})" class="h-2 w-2 rounded-full transition-colors" :class="index === {{ $i }} ? 'bg-red' : 'bg-gold/50'" aria-label="Go to slide {{ $i + 1 }}"></button>@endforeach</div>
        @endif
    </div>
</section>
@endif
