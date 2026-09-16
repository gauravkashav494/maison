<section class="relative min-h-[85svh] overflow-hidden bg-ink text-ivory" x-data="parallax(0.08)">
    <div class="absolute inset-x-0 -top-[10%] h-[120%] will-change-transform" :style="`transform: translateY(${y}%)`">
        <img src="{{ $featured->hero_image_url }}" alt="{{ $featured->name }}" loading="lazy" decoding="async" class="img-cover absolute inset-0 object-[50%_20%]">
    </div>
    <div class="absolute inset-0 bg-gradient-to-r from-ink/80 via-ink/40 to-ink/10"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-ink/60 to-transparent"></div>

    <div class="container-luxe relative flex min-h-[85svh] flex-col justify-between py-16 lg:py-20" x-data x-intersect.once="$el.querySelectorAll('.reveal').forEach(e => e.classList.add('is-visible'))">
        <div class="reveal flex items-center justify-between text-[0.625rem] uppercase tracking-[0.22em] text-ivory/60">
            <span>Featured Collection</span>
            @if($collectionIndex)<span class="tabular-nums">{{ str_pad($collectionIndex, 2, '0', STR_PAD_LEFT) }} / {{ str_pad($collectionTotal, 2, '0', STR_PAD_LEFT) }}</span>@endif
        </div>

        <div class="max-w-2xl">
            @if(!empty($home['featured_eyebrow']))<p class="reveal eyebrow text-gold-light">{{ $home['featured_eyebrow'] }}</p>@endif
            <h2 class="reveal display-xl mt-5 text-balance" style="--reveal-delay: .1s">{{ $featured->name }}</h2>
            <p class="reveal mt-7 max-w-md text-[0.9375rem] leading-relaxed text-ivory/75" style="--reveal-delay: .2s">{{ $featured->description }} {{ $home['featured_text_extra'] ?? '' }}</p>
            <div class="reveal mt-9 flex flex-wrap gap-4" style="--reveal-delay: .3s">
                <a href="{{ $featured->url }}" class="btn btn-light btn-lg">Explore the Collection <x-ico name="arrow-right" :size="14" class="btn-arrow" /></a>
                @if(!empty($home['featured_secondary_label']))
                    <a href="{{ $home['featured_secondary_url'] ?? '#' }}" class="btn btn-outline-light btn-lg">{{ $home['featured_secondary_label'] }}</a>
                @endif
            </div>
        </div>
    </div>
</section>
