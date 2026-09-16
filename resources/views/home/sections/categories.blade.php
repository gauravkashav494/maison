@php
    $spans = ['lg:col-span-5', 'lg:col-span-4', 'lg:col-span-3', 'lg:col-span-3', 'lg:col-span-4', 'lg:col-span-5'];
    $count = $categories->count();
@endphp
<section id="categories" class="container-luxe py-20 lg:py-28">
    <x-section-header :eyebrow="$home['categories_eyebrow'] ?? null" :title="$home['categories_heading'] ?? 'Explore'" :description="$home['categories_text'] ?? null" cta="Shop all" :cta-url="route('shop.index')" />

    <div class="mt-12 grid grid-cols-2 gap-3 lg:grid-cols-12 lg:auto-rows-[30rem] lg:gap-4">
        @foreach($categories as $i => $c)
            @php $wide = $i === 0 || ($i === $count - 1 && $count % 2 === 0); @endphp
            <div class="reveal {{ $spans[$i % 6] }} {{ $wide ? 'col-span-2 aspect-[4/5] sm:aspect-[16/10] lg:aspect-auto' : 'aspect-[3/4] lg:aspect-auto' }}"
                 style="--reveal-delay: {{ ($i % 3) * 0.08 }}s" x-data x-intersect.once="$el.classList.add('is-visible')">
                <a href="{{ $c->url }}" class="group relative block h-full w-full overflow-hidden bg-sand">
                    @if($c->image_url)<img src="{{ $c->image_url }}" alt="{{ $c->name }}" loading="lazy" decoding="async" class="img-cover img-zoom">@endif
                    <div class="absolute inset-0 bg-gradient-to-t from-ink/65 via-ink/5 to-transparent opacity-90 transition-opacity duration-700 group-hover:opacity-100"></div>
                    <div class="absolute inset-x-0 bottom-0 flex items-end justify-between p-5 text-ivory lg:p-7">
                        <div>
                            <p class="eyebrow text-[0.5625rem] text-ivory/60">{{ $c->products_count ?? $c->products()->count() }} pieces</p>
                            <h3 class="mt-1.5 font-serif text-2xl leading-none lg:text-4xl">{{ $c->name }}</h3>
                            @if($c->tagline)<p class="mt-2 hidden text-[0.8125rem] text-ivory/70 sm:block">{{ $c->tagline }}</p>@endif
                        </div>
                        <span aria-hidden="true" class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-ivory/40 transition-all duration-500 group-hover:bg-ivory group-hover:text-ink">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 12L12 2M12 2H5M12 2v7" stroke="currentColor" stroke-width="1.1"/></svg>
                        </span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</section>
