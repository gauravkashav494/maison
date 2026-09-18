@if($bestSellers->isNotEmpty())
<section class="border-y border-line bg-warm">
    <div class="h-container section">
        <x-section-head :eyebrow="$g['bestsellers_eyebrow'] ?? 'Most loved'" :title="$g['bestsellers_heading'] ?? 'Bestsellers'" :href="route('shop.category', 'best-sellers')" label="Shop all bestsellers" />
        <x-rail :products="$bestSellers" />
    </div>
</section>
@endif
