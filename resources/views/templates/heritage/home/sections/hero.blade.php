@php $slides = array_values(array_filter($g['hero_slides'] ?? [], fn ($s) => ! empty($s['heading']))); @endphp
@if($slides)
<section class="h-container pt-4 lg:pt-6" x-data="heroCarousel({{ count($slides) }})" @mouseenter="paused = true" @mouseleave="paused = false" aria-roledescription="carousel">
    <div class="banner-round relative bg-cream-dark">
        @foreach($slides as $i => $s)
            <div x-show="index === {{ $i }}" x-transition:enter="transition duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="grid min-h-[22rem] lg:min-h-[26rem] lg:grid-cols-12" @if($i > 0) x-cloak @endif>
                <div class="relative z-10 flex flex-col justify-center p-6 sm:p-10 lg:col-span-6 lg:p-14">
                    @if(!empty($s['eyebrow']))<p class="eyebrow">{{ $s['eyebrow'] }}</p>@endif
                    <h1 class="display mt-3 text-3xl text-red sm:text-4xl lg:text-[3.25rem]">{{ $s['heading'] }}</h1>
                    @if(!empty($s['text']))<p class="mt-4 inline-block max-w-lg rounded-lg bg-red px-4 py-3 text-sm text-cream sm:text-base">{{ $s['text'] }}</p>@endif
                    @if(!empty($s['badges']))
                        <ul class="mt-6 flex flex-wrap gap-x-6 gap-y-3">
                            @foreach(array_slice((array) $s['badges'], 0, 4) as $b)<li class="flex items-center gap-2 text-xs font-semibold text-ink"><span class="grid h-8 w-8 place-items-center rounded-full border border-gold bg-white text-gold"><x-ico name="leaf" :size="14" /></span>{{ $b }}</li>@endforeach
                        </ul>
                    @endif
                    @if(!empty($s['cta_label']))<div class="mt-6"><a href="{{ $s['cta_url'] ?? '#' }}" class="btn btn-primary rounded-full px-7">{{ $s['cta_label'] }} <x-ico name="arrow-right" :size="16" /></a></div>@endif
                </div>
                <div class="relative min-h-[14rem] lg:col-span-6">
                    @if(!empty($s['image']))<img src="{{ \App\Support\Media::url($s['image']) }}" alt="" class="absolute inset-0 h-full w-full object-cover" @if($i === 0) fetchpriority="high" @else loading="lazy" @endif>@endif
                    <div class="absolute inset-y-0 left-0 hidden w-32 bg-gradient-to-r from-cream-dark to-transparent lg:block"></div>
                </div>
            </div>
        @endforeach
        @if(count($slides) > 1)
            <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">@foreach($slides as $i => $s)<button type="button" @click="go({{ $i }})" class="h-2 w-2 rounded-full transition-colors" :class="index === {{ $i }} ? 'bg-red' : 'bg-gold/50'" aria-label="Go to slide {{ $i + 1 }}"></button>@endforeach</div>
        @endif
    </div>
</section>
@endif
