@if($newArrivals->isNotEmpty())
<section class="g-container section">
    <x-section-head :title="$g['new_heading'] ?? 'New in store'" :text="$g['new_text'] ?? null" :href="route('shop.category', 'new-arrivals')" />
    <x-rail :products="$newArrivals" />
</section>
@endif
