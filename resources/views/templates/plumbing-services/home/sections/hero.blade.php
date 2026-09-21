{{-- Hero: rotating promotional banners. Phones: rounded card carousel under the app header. Desktop: full-width split hero with trust strip and stats. --}}
@php
    $slides = collect($g['hero_slides'] ?? [])->filter(fn ($s) => ! empty($s['title']))->values();
    $biz = template()->contact();
    $site = tsetting('site');
    $secondary = fn ($s) => match ($s['secondary_action'] ?? 'call') { 'whatsapp' => $biz['whatsapp_href'], 'emergency' => route('services.emergency'), 'quote' => route('quote.create'), default => $biz['phone_href'] };
    $secondaryIcon = fn ($s) => match ($s['secondary_action'] ?? 'call') { 'whatsapp' => 'whatsapp', 'emergency' => 'alert', 'quote' => 'file', default => 'phone' };
@endphp
@if($slides->isNotEmpty())
<section class="ps-container pt-3 lg:pt-8" x-data="heroCarousel({{ $slides->count() }})" @mouseenter="pause()" @mouseleave="play()" @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)" aria-roledescription="carousel">
    <div class="relative grid">
        @foreach($slides as $i => $s)
            <div x-show="index === {{ $i }}" x-transition.opacity.duration.500ms class="band-deep relative col-start-1 row-start-1 overflow-hidden rounded-3xl" role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $slides->count() }}">
                @if(!empty($s['image']))<img src="{{ $s['image'] }}" alt="" {{ $i === 0 ? 'fetchpriority=high' : 'loading=lazy' }} decoding="async" class="absolute inset-0 h-full w-full object-cover opacity-30 lg:left-auto lg:right-0 lg:w-[52%] lg:opacity-100" style="mask-image: linear-gradient(to right, transparent 0%, black 40%); -webkit-mask-image: linear-gradient(to right, transparent 0%, black 40%)">@endif
                <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-bright/40 blur-3xl"></div>
                <div class="relative flex min-h-[15rem] flex-col justify-end px-5 pb-6 pt-8 sm:min-h-[18rem] lg:min-h-[30rem] lg:max-w-[52%] lg:justify-center lg:px-14 lg:py-16">
                    @if(!empty($s['eyebrow']))<p class="eyebrow text-accent">{{ $s['eyebrow'] }}</p>@endif
                    <h1 class="mt-2 font-display text-[1.75rem] font-extrabold leading-[1.1] text-balance sm:text-4xl lg:text-[3.25rem]">{{ $s['title'] }}</h1>
                    @if(!empty($s['text']))<p class="mt-2 max-w-md text-sm text-white/85 lg:mt-4 lg:text-lg">{{ $s['text'] }}</p>@endif
                    <div class="mt-4 flex flex-wrap gap-2 lg:mt-7 lg:gap-3">
                        @if(!empty($s['cta_label']))<a href="{{ $s['cta_url'] ?: route('booking.create') }}" class="btn btn-accent lg:btn-lg">{{ $s['cta_label'] }}</a>@endif
                        @if(!empty($s['secondary_label']))<a href="{{ $secondary($s) }}" class="btn btn-glass lg:btn-lg" @if(($s['secondary_action'] ?? '') === 'whatsapp') target="_blank" rel="noopener" @endif><x-ico :name="$secondaryIcon($s)" :size="18" /> {{ $s['secondary_label'] }}</a>@endif
                    </div>
                    {{-- Desktop trust chips --}}
                    <ul class="mt-8 hidden flex-wrap gap-x-6 gap-y-2 text-sm text-white/85 lg:flex">
                        @foreach(array_slice($site['usp_strip'] ?? [], 0, 4) as $usp)<li class="flex items-center gap-2"><span class="grid h-5 w-5 place-items-center rounded-full bg-white/15"><x-ico name="check" :size="12" class="text-accent" /></span> {{ $usp }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endforeach
        @if($slides->count() > 1)
            <div class="absolute bottom-3 right-4 z-10 flex items-center gap-2 lg:bottom-6 lg:right-8">
                <div class="flex items-center gap-1.5">@foreach($slides as $i => $s)<button type="button" @click="go({{ $i }})" class="h-1.5 rounded-full bg-white/50 transition-all" :class="index === {{ $i }} ? 'w-6 bg-white' : 'w-1.5'" aria-label="Show slide {{ $i + 1 }}"></button>@endforeach</div>
                <button type="button" @click="prev()" class="ml-2 hidden h-10 w-10 place-items-center rounded-full bg-white/15 text-white ring-1 ring-white/40 backdrop-blur hover:bg-white/30 lg:grid" aria-label="Previous slide"><x-ico name="chevron-left" :size="20" /></button>
                <button type="button" @click="next()" class="hidden h-10 w-10 place-items-center rounded-full bg-white/15 text-white ring-1 ring-white/40 backdrop-blur hover:bg-white/30 lg:grid" aria-label="Next slide"><x-ico name="chevron-right" :size="20" /></button>
            </div>
        @endif
    </div>

    {{-- Desktop stats band --}}
    @if(!empty($site['stats']))
        <ul class="mt-6 hidden grid-cols-4 gap-4 lg:grid">
            @foreach(array_slice($site['stats'], 0, 4) as $stat)
                <li class="card flex items-center gap-4 p-5"><span class="font-display text-3xl font-extrabold text-primary">{{ $stat['value'] }}</span><span class="text-sm font-semibold text-slate">{{ $stat['label'] }}</span></li>
            @endforeach
        </ul>
    @endif
</section>
@endif