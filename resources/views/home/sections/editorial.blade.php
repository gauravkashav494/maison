@php
    $img1 = \App\Support\Media::url($home['editorial_image'] ?? null);
    $img2 = \App\Support\Media::url($home['editorial_image_secondary'] ?? null);
@endphp
<section class="container-luxe overflow-hidden py-20 lg:py-32">
    <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-8">
        {{-- Imagery — primary with an offset secondary frame --}}
        <div class="relative lg:col-span-7">
            @if($img1)
                <div class="img-reveal relative aspect-[4/5] w-[86%] bg-sand sm:aspect-[5/6] lg:w-[80%]" x-data x-intersect.once.threshold.25="$el.classList.add('is-visible')">
                    <div class="img-reveal-clip"><img src="{{ $img1 }}" alt="" loading="lazy" decoding="async" class="img-cover absolute inset-0"></div>
                </div>
            @endif
            @if($img2)
                <div class="img-reveal absolute -bottom-10 right-0 aspect-[3/4] w-[46%] bg-sand shadow-[0_40px_80px_-30px_rgba(21,20,18,0.35)] lg:-bottom-14 lg:w-[40%]" data-direction="left" style="--reveal-delay: 0.25s" x-data x-intersect.once.threshold.25="$el.classList.add('is-visible')">
                    <div class="img-reveal-clip"><img src="{{ $img2 }}" alt="" loading="lazy" decoding="async" class="img-cover absolute inset-0"></div>
                </div>
            @endif
            @if(!empty($home['editorial_caption']))
                <p class="reveal absolute -left-4 bottom-0 hidden origin-bottom-left -rotate-90 whitespace-nowrap text-[0.625rem] uppercase tracking-[0.3em] text-taupe lg:block" style="--reveal-delay: 0.5s" x-data x-intersect.once="$el.classList.add('is-visible')">{{ $home['editorial_caption'] }}</p>
            @endif
        </div>

        {{-- Copy --}}
        <div class="mt-8 lg:col-span-4 lg:col-start-9 lg:mt-0" x-data x-intersect.once="$el.querySelectorAll('.reveal').forEach(e => e.classList.add('is-visible'))">
            @if(!empty($home['editorial_eyebrow']))<p class="reveal eyebrow text-taupe">{{ $home['editorial_eyebrow'] }}</p>@endif
            <h2 class="reveal display-lg mt-5 text-balance" style="--reveal-delay: .1s">{!! emph($home['editorial_heading'] ?? '') !!}</h2>
            <p class="reveal mt-7 text-[0.9375rem] leading-relaxed text-smoke text-pretty" style="--reveal-delay: .2s">{{ $home['editorial_text'] ?? '' }}</p>
            @if(!empty($home['editorial_cta_label']))
                <div class="reveal mt-9" style="--reveal-delay: .3s">
                    <a href="{{ $home['editorial_cta_url'] ?? '#' }}" class="btn btn-outline btn-lg">{{ $home['editorial_cta_label'] }} <x-ico name="arrow-right" :size="14" class="btn-arrow" /></a>
                </div>
            @endif
            @if(!empty($home['editorial_stats']))
                <div class="reveal mt-10 grid grid-cols-3 gap-6 border-t border-ink/10 pt-6" style="--reveal-delay: .4s">
                    @foreach($home['editorial_stats'] as $stat)
                        <div>
                            <p class="font-serif text-3xl">{{ $stat['value'] ?? '' }}</p>
                            <p class="mt-1 text-[0.625rem] uppercase tracking-[0.2em] text-taupe">{{ $stat['label'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
