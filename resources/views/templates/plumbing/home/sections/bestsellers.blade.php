@if($bestSellers->isNotEmpty())
<section class="p-container section">
    <x-section-head :title="$g['bestsellers_heading'] ?? 'Bestsellers'" :text="$g['bestsellers_text'] ?? null" :href="route('shop.category', 'best-sellers')" />
    <x-rail :products="$bestSellers" />
</section>
@endif
