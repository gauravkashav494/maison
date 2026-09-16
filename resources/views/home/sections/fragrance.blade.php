@php
    $img = \App\Support\Media::url($home['fragrance_image'] ?? null) ?: $fragrance->image_url;
    $size = $home['fragrance_size'] ?? ($fragrance->sizes[0] ?? null);
@endphp
<section class="grid lg:grid-cols-2">
    <div class="img-reveal relative aspect-[4/5] bg-charcoal sm:aspect-[16/11] lg:aspect-auto lg:min-h-[44rem]" x-data x-intersect.once.threshold.25="$el.classList.add('is-visible')">
        <div class="img-reveal-clip">
            <img src="{{ $img }}" alt="{{ $fragrance->name }}" loading="lazy" decoding="async" class="img-cover absolute inset-0">
        </div>
        <div class="absolute inset-0 bg-gradient-to-t from-ink/40 to-transparent lg:bg-gradient-to-r lg:from-transparent lg:to-charcoal/30"></div>
        @if(!empty($home['fragrance_caption']))<p class="absolute bottom-6 left-6 text-[0.625rem] uppercase tracking-[0.22em] text-ivory/70 lg:bottom-8 lg:left-8">{{ $home['fragrance_caption'] }}</p>@endif
    </div>

    <div class="flex flex-col justify-center bg-charcoal px-6 py-16 text-ivory sm:px-10 lg:px-20 lg:py-24" x-data x-intersect.once="$el.querySelectorAll('.reveal').forEach(e => e.classList.add('is-visible'))">
        @if(!empty($home['fragrance_eyebrow']))<p class="reveal eyebrow text-gold-light">{{ $home['fragrance_eyebrow'] }}</p>@endif
        <h2 class="reveal display-lg mt-5" style="--reveal-delay: .1s">{!! emph($home['fragrance_heading'] ?? $fragrance->name) !!}</h2>
        @if(!empty($home['fragrance_subheading']))<p class="reveal mt-3 font-serif text-xl text-ivory/70" style="--reveal-delay: .15s">{{ $home['fragrance_subheading'] }}</p>@endif
        <p class="reveal mt-7 max-w-md text-[0.9375rem] leading-relaxed text-ivory/70 text-pretty" style="--reveal-delay: .2s">{{ $home['fragrance_text'] ?? $fragrance->description }}</p>

        @if(!empty($home['fragrance_notes']))
            <div class="reveal mt-9 divide-y divide-ivory/10 border-y border-ivory/10" style="--reveal-delay: .3s">
                @foreach($home['fragrance_notes'] as $note)
                    <div class="grid grid-cols-[5rem_1fr] gap-4 py-3.5 text-sm">
                        <span class="text-[0.625rem] uppercase tracking-[0.22em] text-ivory/50">{{ $note['label'] ?? '' }}</span>
                        <span class="text-ivory/85">{{ $note['value'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="reveal mt-9 flex flex-wrap items-center gap-4" style="--reveal-delay: .4s">
            <a href="{{ $fragrance->url }}" class="btn btn-light btn-lg">Discover the Fragrance <x-ico name="arrow-right" :size="14" class="btn-arrow" /></a>
            @if($size)
                <button type="button" @click="$store.cart.add({{ $fragrance->id }}, @js($size))" class="link-underline pb-0.5 text-[0.6875rem] uppercase tracking-[0.2em] text-ivory/80 hover:text-ivory">Add {{ $size }} — {{ money($fragrance->price) }}</button>
            @endif
        </div>
    </div>
</section>
