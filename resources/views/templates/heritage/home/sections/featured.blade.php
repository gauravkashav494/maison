@if($featuredProducts->isNotEmpty())
<section class="h-container section !pt-0">
    <x-section-head :eyebrow="$g['featured_eyebrow'] ?? 'Featured'" :title="$g['featured_heading'] ?? 'This week’s picks'" :text="$g['featured_text'] ?? null" :href="route('shop.category', 'new-arrivals')" label="See all new launches" />
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @foreach($featuredProducts->take(8) as $product)
            <x-product-card :product="$product" :priority="$loop->index < 4" />
        @endforeach
    </div>
</section>
@endif
