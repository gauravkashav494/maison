@php
    $heroImage = \App\Support\Media::url($home['hero_image'] ?? null);
    $d = fn (float $s) => "animation-delay: {$s}s";
@endphp
<section class="relative min-h-[100svh] w-full overflow-hidden bg-ink text-ivory">
    {{-- Campaign image — full bleed on mobile, right 7/12 column on desktop --}}
    <div class="absolute inset-0 animate-hero-img lg:left-[calc(var(--edge)+(min(100vw,var(--container-max))-2*var(--gutter))*5/12)]">
        @if($heroImage)
            <img src="{{ $heroImage }}" alt="{{ $home['hero_image_alt'] ?? '' }}" fetchpriority="high" decoding="async"
                 class="img-cover absolute inset-0 object-[50%_15%] lg:object-[50%_28%]">
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/30 to-ink/20 lg:hidden"></div>
        <div class="absolute inset-x-0 top-0 hidden h-40 bg-gradient-to-b from-ink/60 to-transparent lg:block"></div>
        <div class="absolute inset-x-0 bottom-0 hidden h-32 bg-gradient-to-t from-ink/50 to-transparent lg:block"></div>
    </div>

    <div class="container-luxe relative grid min-h-[100svh] lg:grid-cols-12">
        {{-- Copy panel --}}
        <div class="relative flex flex-col justify-end pb-24 pt-40 lg:col-span-5 lg:justify-center lg:border-r lg:border-ivory/10 lg:pb-28 lg:pr-12 lg:pt-36 2xl:pr-16">
            <div class="flex items-center gap-4 animate-hero-in" style="{{ $d(0.4) }}">
                <span class="h-px w-10 shrink-0 bg-gold"></span>
                <p class="eyebrow text-ivory/70">
                    <span class="sm:hidden">{{ $home['hero_eyebrow_short'] ?? $home['hero_eyebrow'] ?? '' }}</span>
                    <span class="hidden sm:inline">{{ $home['hero_eyebrow'] ?? '' }}</span>
                </p>
            </div>
            <h1 class="display-xl mt-7 text-balance animate-hero-in lg:text-[clamp(2.75rem,4.7vw,6.25rem)]" style="{{ $d(0.55) }}">{!! emph($home['hero_heading'] ?? '') !!}</h1>
            <p class="mt-7 max-w-md text-[0.9375rem] leading-relaxed text-ivory/70 text-pretty animate-hero-in" style="{{ $d(0.75) }}">{{ $home['hero_text'] ?? '' }}</p>
            <div class="mt-9 flex flex-wrap items-center gap-x-8 gap-y-4 animate-hero-in" style="{{ $d(0.9) }}">
                @if(!empty($home['hero_primary_label']))
                    <a href="{{ $home['hero_primary_url'] ?? '#' }}" class="btn btn-light btn-lg">{{ $home['hero_primary_label'] }} <x-ico name="arrow-right" :size="14" class="btn-arrow" /></a>
                @endif
                @if(!empty($home['hero_secondary_label']))
                    <a href="{{ $home['hero_secondary_url'] ?? '#' }}" class="link-underline eyebrow inline-flex items-center gap-2 pb-0.5 text-ivory">{{ $home['hero_secondary_label'] }} <span aria-hidden="true">→</span></a>
                @endif
            </div>

            {{-- Caption — inline on mobile, pinned to the panel foot on desktop --}}
            <div class="mt-12 flex items-end justify-between border-t border-ivory/15 pt-5 text-[0.625rem] uppercase tracking-[0.22em] text-ivory/55 animate-hero-in lg:absolute lg:bottom-10 lg:left-0 lg:right-12 lg:mt-0 lg:border-0 lg:pt-0 2xl:right-16" style="{{ $d(1.2) }}">
                <p>{{ $home['hero_caption'] ?? '' }}</p>
                <a href="#categories" class="group hidden items-center gap-3 hover:text-ivory sm:flex">
                    Scroll
                    <span class="relative block h-10 w-px overflow-hidden bg-ivory/20"><span class="absolute inset-x-0 top-0 h-1/2 animate-scrollcue bg-ivory"></span></span>
                </a>
            </div>
        </div>

        {{-- Image column caption (desktop) --}}
        <div class="relative hidden lg:col-span-7 lg:block">
            @if(!empty($home['hero_look_title']))
                <div class="absolute bottom-10 right-0 text-right text-[0.625rem] uppercase tracking-[0.22em] text-ivory/70 animate-hero-in" style="{{ $d(1.3) }}">
                    <p class="font-serif text-2xl normal-case tracking-normal text-ivory">{{ $home['hero_look_title'] }}</p>
                    <p class="mt-1">{{ $home['hero_look_text'] ?? '' }}</p>
                </div>
            @endif
        </div>
    </div>
</section>
