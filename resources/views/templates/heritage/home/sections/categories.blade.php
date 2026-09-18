@if($categories->isNotEmpty())
<section class="h-container section">
    <x-section-head :eyebrow="$g['categories_eyebrow'] ?? 'Shop by category'" :title="$g['categories_heading'] ?? 'Everything a good kitchen needs'" :href="route('shop.index')" label="Shop all products" />
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach($categories->take($categoriesLimit) as $c)
            <a href="{{ $c->url }}" class="group relative overflow-hidden rounded-xl bg-maroon text-cream {{ $loop->first ? 'sm:col-span-2 sm:row-span-2' : '' }}">
                <div class="{{ $loop->first ? 'aspect-square sm:aspect-auto sm:h-full' : 'aspect-square' }} overflow-hidden">@if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover transition-transform duration-700 ease-[var(--ease-soft)] group-hover:scale-105" loading="lazy">@endif</div>
                <div class="absolute inset-0 bg-gradient-to-t from-maroon-deep/90 via-maroon-deep/20 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 p-4 {{ $loop->first ? 'sm:p-7' : '' }}">
                    <p class="font-serif {{ $loop->first ? 'text-2xl sm:text-3xl' : 'text-lg' }} font-semibold leading-tight">{{ $c->name }}</p>
                    <p class="mt-0.5 line-clamp-1 text-xs text-cream/75 {{ $loop->first ? 'sm:text-sm' : '' }}">{{ $c->tagline }}</p>
                    <span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-gold-light opacity-0 transition-opacity group-hover:opacity-100">Shop now <x-ico name="arrow-right" :size="12" /></span>
                </div>
                <span class="pointer-events-none absolute inset-2 rounded-lg border border-gold/0 transition-colors duration-500 group-hover:border-gold/60"></span>
            </a>
        @endforeach
    </div>
</section>
@endif
