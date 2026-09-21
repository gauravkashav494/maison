@if($featured->isNotEmpty())
<section class="p-container section">
    <x-section-head :title="$g['featured_heading'] ?? 'Featured products'" :text="$g['featured_text'] ?? null" :href="route('shop.category', 'best-sellers')" />
    <x-rail :products="$featured" />
</section>
@endif
