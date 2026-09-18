@if($needs->isNotEmpty())
<section class="bg-maroon-deep text-cream">
    <div class="h-container section">
        <div class="mb-8 text-center lg:mb-10">
            <p class="eyebrow eyebrow-center text-gold-light">{{ $g['needs_eyebrow'] ?? 'Shop by need' }}</p>
            <h2 class="section-title mt-3 text-cream">{{ $g['needs_heading'] ?? 'Built around how you cook' }}</h2>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
            @foreach($needs as $n)
                <a href="{{ $n->url }}" class="group relative overflow-hidden rounded-xl border border-cream/10 bg-maroon">
                    <div class="aspect-[4/3] overflow-hidden">@if($n->image_url)<img src="{{ $n->image_url }}" alt="" class="img-cover opacity-90 transition-transform duration-700 ease-[var(--ease-soft)] group-hover:scale-105" loading="lazy">@endif</div>
                    <div class="absolute inset-0 bg-gradient-to-t from-maroon-deep via-maroon-deep/30 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-4 sm:p-5">
                        <p class="font-serif text-lg font-semibold leading-tight sm:text-xl">{{ $n->name }}</p>
                        <p class="mt-1 line-clamp-2 text-xs text-cream/75 sm:text-sm">{{ $n->description }}</p>
                        <span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-gold-light">{{ $n->products_count }} products <x-ico name="arrow-right" :size="12" /></span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
