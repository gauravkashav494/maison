@php
    $popular = ['Wool coat', 'Amber oud', 'Automatic watch', 'Leather tote', 'Belt', 'Gift'];
    $searchCategories = \App\Models\Category::active()->topLevel()->get(['name', 'slug']);
    $suggested = \App\Models\Product::with('category')->active()->bestSellers()->orderBy('sort_order')->limit(4)->get();
@endphp
<div x-data="search" x-cloak x-show="$store.ui.searchOpen" class="fixed inset-0 z-[70]"
     x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <button type="button" aria-label="Close search" class="absolute inset-0 bg-ink/40" @click="$store.ui.closeAll()"></button>
    <div x-show="$store.ui.searchOpen" x-trap.noscroll="$store.ui.searchOpen"
         x-transition:enter="transition duration-500 ease-[var(--ease-luxe)]" x-transition:enter-start="-translate-y-6 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="-translate-y-4 opacity-0"
         class="absolute inset-x-0 top-0 max-h-[100dvh] overflow-y-auto bg-ivory text-ink" role="dialog" aria-modal="true" aria-label="Search">
        <div class="container-luxe py-6 lg:py-10">
            <form @submit.prevent="submit()" class="flex items-center gap-4 border-b border-ink pb-3">
                <x-ico name="search" :size="22" />
                <input x-ref="input" x-model="q" type="search" placeholder="Search products, categories, collections…" aria-label="Search" autocomplete="off"
                       class="w-full bg-transparent font-serif text-2xl outline-none placeholder:text-taupe lg:text-4xl">
                <button type="button" @click="$store.ui.closeAll()" aria-label="Close" class="grid h-10 w-10 shrink-0 place-items-center hover:opacity-60"><x-ico name="close" :size="22" /></button>
            </form>

            <div class="mt-8 grid gap-10 lg:grid-cols-12 lg:gap-14">
                <div class="space-y-8 lg:col-span-4">
                    {{-- Category matches --}}
                    <div x-show="q.trim() && results.categories.length" x-cloak>
                        <p class="eyebrow mb-4 text-taupe">Categories</p>
                        <ul class="space-y-2">
                            <template x-for="c in results.categories" :key="c.url">
                                <li><a :href="c.url" class="link-underline font-serif text-xl" x-text="c.name"></a></li>
                            </template>
                        </ul>
                    </div>

                    <template x-if="!q.trim()">
                        <div class="space-y-8">
                            <div>
                                <p class="eyebrow mb-4 text-taupe">Popular searches</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($popular as $term)
                                        <button type="button" @click="submit(@js($term))" class="border border-ink/15 px-3 py-1.5 text-[0.75rem] transition-colors hover:border-ink">{{ $term }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <p class="eyebrow mb-4 text-taupe">Categories</p>
                                <ul class="grid grid-cols-2 gap-y-2.5">
                                    @foreach($searchCategories as $c)
                                        <li><a href="{{ route('shop.category', $c->slug) }}" class="link-underline text-sm">{{ $c->name }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                            <div x-show="$store.recent.terms.length">
                                <div class="mb-4 flex items-center justify-between">
                                    <p class="eyebrow text-taupe">Recent</p>
                                    <button type="button" @click="$store.recent.clear()" class="text-[0.625rem] uppercase tracking-widest text-smoke hover:text-ink">Clear</button>
                                </div>
                                <ul class="space-y-2">
                                    <template x-for="r in $store.recent.terms" :key="r">
                                        <li><button type="button" @click="submit(r)" class="flex items-center gap-2 text-sm hover:text-smoke"><x-ico name="clock" :size="13" class="text-taupe" /> <span x-text="r"></span></button></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </template>

                    <button type="button" x-show="q.trim()" x-cloak @click="submit()" class="link-underline eyebrow inline-flex items-center gap-2">
                        See all results for “<span x-text="q"></span>” <x-ico name="arrow-up-right" :size="12" />
                    </button>
                </div>

                <div class="lg:col-span-8">
                    <p class="eyebrow mb-4 text-taupe" x-text="q.trim() ? (results.products.length ? 'Products' : (loading ? 'Searching…' : 'No products found')) : 'Suggested for you'"></p>

                    {{-- Live results --}}
                    <div x-show="q.trim()" x-cloak class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        <template x-for="p in results.products" :key="p.id">
                            <a :href="p.url" class="group">
                                <div class="relative aspect-[3/4] overflow-hidden bg-sand"><img :src="p.images[0]" :alt="p.name" class="img-cover img-zoom"></div>
                                <p class="mt-3 text-[0.5625rem] uppercase tracking-[0.2em] text-taupe" x-text="p.category"></p>
                                <p class="mt-1 font-serif text-base leading-tight" x-text="p.name"></p>
                                <p class="mt-1 text-xs tabular-nums" x-text="p.price_formatted"></p>
                            </a>
                        </template>
                    </div>
                    <p x-show="q.trim() && !loading && !results.products.length" x-cloak class="mt-2 max-w-sm text-sm text-smoke">Try a different term, or browse a category on the left.</p>

                    {{-- Suggested (server-rendered) --}}
                    <div x-show="!q.trim()" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach($suggested as $p)
                            <a href="{{ $p->url }}" class="group">
                                <div class="relative aspect-[3/4] overflow-hidden bg-sand"><img src="{{ $p->image_url }}" alt="{{ $p->name }}" loading="lazy" class="img-cover img-zoom"></div>
                                <p class="mt-3 text-[0.5625rem] uppercase tracking-[0.2em] text-taupe">{{ $p->category?->name }}</p>
                                <p class="mt-1 font-serif text-base leading-tight">{{ $p->name }}</p>
                                <p class="mt-1 text-xs tabular-nums">{{ money($p->price) }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
