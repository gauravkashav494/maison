@if($fresh->isNotEmpty())
<section class="g-container section">
    <x-section-head :title="$g['fresh_heading'] ?? 'Fresh from the farm'" :text="$g['fresh_text'] ?? null" :href="route('shop.category', $g['fresh_category_slug'] ?? 'fruits-vegetables')" />
    <x-rail :products="$fresh" />
</section>
@endif
