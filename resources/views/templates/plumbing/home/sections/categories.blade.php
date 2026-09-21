{{-- Visual category grid: image cards with name + sub-category hint --}}
@if($categories->isNotEmpty())
<section id="categories" class="p-container section">
    <x-section-head :title="$g['categories_heading'] ?? 'Shop by category'" :text="$g['categories_text'] ?? null" :href="route('shop.index')" link="All products" />
    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-5 lg:gap-4">
        @foreach($categories->take($categoriesLimit) as $c)
            <a href="{{ $c->url }}" class="cat-card group">
                <span class="block aspect-[5/4] overflow-hidden bg-sky">@if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif</span>
                <span class="block p-3">
                    <span class="block text-sm font-bold leading-tight group-hover:text-primary">{{ $c->name }}</span>
                    <span class="mt-1 hidden text-xs text-slate sm:block">{{ $c->children->isNotEmpty() ? $c->children->take(3)->pluck('name')->join(', ') : $c->tagline }}</span>
                </span>
            </a>
        @endforeach
        <a href="{{ route('shop.index') }}" class="cat-card flex flex-col items-center justify-center gap-2 bg-primary p-4 text-center text-white hover:bg-deep">
            <span class="grid h-11 w-11 place-items-center rounded-full bg-white/15"><x-ico name="grid" :size="22" /></span>
            <span class="text-sm font-bold">All products</span>
            <span class="text-xs text-white/75">Browse the full catalogue</span>
        </a>
    </div>
</section>
@endif
