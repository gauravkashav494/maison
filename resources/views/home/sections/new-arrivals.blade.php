<section class="border-t border-ink/10 bg-cream">
    <div class="container-luxe py-20 lg:py-28">
        <x-section-header :eyebrow="$home['arrivals_eyebrow'] ?? null" :title="$home['arrivals_heading'] ?? 'New Arrivals'" :description="$home['arrivals_text'] ?? null" cta="View all new arrivals" :cta-url="route('shop.category', 'new-arrivals')" />

        <div class="mt-12 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-6 lg:gap-y-14">
            @foreach($newArrivals as $i => $p)
                <div class="reveal" style="--reveal-delay: {{ ($i % 4) * 0.07 }}s" x-data x-intersect.once.threshold.10="$el.classList.add('is-visible')">
                    <x-product-card :product="$p" />
                </div>
            @endforeach
        </div>

        <div class="reveal mt-14 flex justify-center" x-data x-intersect.once="$el.classList.add('is-visible')">
            <a href="{{ route('shop.category', 'new-arrivals') }}" class="btn btn-outline btn-lg">View All New Arrivals <x-ico name="arrow-right" :size="14" class="btn-arrow" /></a>
        </div>
    </div>
</section>
