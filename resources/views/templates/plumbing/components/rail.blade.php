{{-- Horizontal product rail with arrow buttons on desktop --}}
@props(['products', 'compact' => true])
<div x-data="rail()" class="relative">
    <div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0">
        @foreach($products as $product)
            <x-product-card :product="$product" :compact="$compact" />
        @endforeach
        {{ $slot }}
    </div>
    <button type="button" @click="scroll(-1)" :disabled="!canPrev" class="rail-btn absolute -left-5 top-[38%] hidden lg:grid" aria-label="Scroll left"><x-ico name="chevron-left" :size="18" /></button>
    <button type="button" @click="scroll(1)" :disabled="!canNext" class="rail-btn absolute -right-5 top-[38%] hidden lg:grid" aria-label="Scroll right"><x-ico name="chevron-right" :size="18" /></button>
</div>
