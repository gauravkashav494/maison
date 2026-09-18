{{-- Pill tabs + (tile card, product carousel) per tab. Expects $title, $tabs (model + products), $id. --}}
@if($tabs->isNotEmpty())
<section class="h-container section !py-8 lg:!py-10" x-data="{ tab: 0 }">
    <h2 class="title-c">{{ $title }}</h2>
    <div class="no-scrollbar mt-6 overflow-x-auto pb-1"><div class="mx-auto flex w-max max-w-none gap-2">
        @foreach($tabs as $i => $t)<button type="button" @click="tab = {{ $i }}" class="pill-tab" :class="tab === {{ $i }} && 'is-active'">{{ $t['model']->name }}</button>@endforeach
    </div></div>
    @foreach($tabs as $i => $t)
        @php $m = $t['model']; $url = $m instanceof \App\Models\Collection ? route('collections.show', $m->slug) : $m->url; $blurb = $m->description ?? $m->tagline ?? ''; @endphp
        <div x-show="tab === {{ $i }}" class="mt-6" @if($i > 0) x-cloak @endif>
            <div x-data="rail()" class="relative">
                <div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0">
                    <a href="{{ $url }}" class="tile-card w-[11.5rem] sm:w-[13.5rem] lg:w-[15.25rem]" data-slide>
                        <div class="aspect-[3/4] overflow-hidden">@if($m->image_url)<img src="{{ $m->image_url }}" alt="" class="h-full w-full object-cover" loading="lazy">@endif</div>
                        <div class="absolute inset-0 bg-gradient-to-t from-maroon-deep/90 via-maroon-deep/30 to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-4 text-center text-cream">
                            <p class="font-serif text-xl font-semibold leading-tight">{{ $m->name }}</p>
                            @if($blurb)<p class="mt-1 line-clamp-3 text-xs text-cream/85">{{ $blurb }}</p>@endif
                            <span class="mt-2 inline-block text-xs font-semibold underline underline-offset-4 decoration-gold">View All</span>
                        </div>
                    </a>
                    @foreach($t['products'] as $product)<x-product-card :product="$product" :compact="true" />@endforeach
                </div>
                <button type="button" @click="scroll(-1)" :disabled="!canPrev" class="rail-btn absolute -left-5 top-[40%] hidden lg:grid" aria-label="Scroll left"><x-ico name="chevron-left" :size="18" /></button>
                <button type="button" @click="scroll(1)" :disabled="!canNext" class="rail-btn absolute -right-5 top-[40%] hidden lg:grid" aria-label="Scroll right"><x-ico name="chevron-right" :size="18" /></button>
            </div>
            <p class="text-center"><a href="{{ $url }}" class="view-all">View All</a></p>
        </div>
    @endforeach
</section>
@endif
