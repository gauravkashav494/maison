@if($categories->isNotEmpty())
<section class="g-container section">
    <x-section-head :title="$g['categories_heading'] ?? 'Shop by category'" :href="route('shop.index')" label="All products" />
    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
        @foreach($categories->take($categoriesLimit) as $c)
            <a href="{{ $c->url }}" class="card card-hover group flex flex-col items-center p-3 text-center">
                <span class="aspect-square w-full overflow-hidden rounded-xl bg-leaf-light">@if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">@endif</span>
                <span class="mt-2.5 line-clamp-2 text-[0.8125rem] font-bold leading-snug group-hover:text-leaf">{{ $c->name }}</span>
            </a>
        @endforeach
    </div>
</section>
@endif
