@if($newArrivals->isNotEmpty())
<section class="p-container section">
    <x-section-head :title="$g['new_heading'] ?? 'New arrivals'" :text="$g['new_text'] ?? null" :href="route('shop.category', 'new-arrivals')" />
    <x-rail :products="$newArrivals" />
</section>
@endif
