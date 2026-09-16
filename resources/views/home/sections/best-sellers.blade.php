<section class="container-luxe py-20 lg:py-28">
    <x-section-header :eyebrow="$home['bestsellers_eyebrow'] ?? null" :title="$home['bestsellers_heading'] ?? 'Best Sellers'" :description="$home['bestsellers_text'] ?? null" cta="Shop best sellers" :cta-url="route('shop.category', 'best-sellers')" />

    <div class="reveal relative mt-12" x-data="carousel" x-intersect.once.threshold.10="$el.classList.add('is-visible')">
        <div x-ref="track" class="no-scrollbar -mx-5 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth px-5 scroll-pl-5 sm:-mx-8 sm:gap-6 sm:px-8 sm:scroll-pl-8 lg:-mx-14 lg:px-14 lg:scroll-pl-14 2xl:-mx-20 2xl:px-20 2xl:scroll-pl-20">
            @foreach($bestSellers as $p)
                <div data-slide class="w-[70vw] shrink-0 snap-start sm:w-[42vw] lg:w-[calc((100%-4.5rem)/4)]">
                    <x-product-card :product="$p" :show-rating="true" />
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex items-center justify-between gap-6">
            <div class="relative h-px flex-1 bg-ink/10"><div class="absolute inset-y-0 left-0 bg-ink transition-[width] duration-300" :style="`width:${Math.max(15, progress * 100)}%`"></div></div>
            <div class="flex gap-2">
                <button type="button" @click="scroll(-1)" :disabled="!canPrev" aria-label="Previous" class="grid h-11 w-11 place-items-center border border-ink/20 transition-colors hover:border-ink hover:bg-ink hover:text-ivory disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-ink"><x-ico name="arrow-left" :size="16" /></button>
                <button type="button" @click="scroll(1)" :disabled="!canNext" aria-label="Next" class="grid h-11 w-11 place-items-center border border-ink/20 transition-colors hover:border-ink hover:bg-ink hover:text-ivory disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-ink"><x-ico name="arrow-right" :size="16" /></button>
            </div>
        </div>
    </div>
</section>
