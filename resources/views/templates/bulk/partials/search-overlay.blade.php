{{-- Full-screen search for phones --}}
@php $p = tsetting('site'); @endphp
<div x-data="search(@js($p['search_suggestions'] ?? []))" x-show="$store.ui.searchOpen" x-cloak class="fixed inset-0 z-[80] flex flex-col bg-white" x-transition.opacity.duration.150ms>
    <form @submit.prevent="submit()" role="search" class="flex items-center gap-2 border-b border-line px-3 py-2.5">
        <button type="button" @click="$store.ui.closeAll()" class="icon-btn" aria-label="Close search"><x-ico name="arrow-left" :size="22" /></button>
        <div class="relative flex-1">
            <x-ico name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate" />
            <input x-ref="input" x-model="q" type="search" :placeholder="placeholder" autocomplete="off" aria-label="Search products" class="field h-11 rounded-xl bg-canvas pl-10 pr-3">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Go</button>
    </form>
    <div class="flex-1 overflow-y-auto p-3">
        <template x-if="!q.trim()">
            <div>
                <template x-if="$store.recent.terms.length">
                    <div class="mb-5">
                        <div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-mist">Recent searches</p><button type="button" @click="$store.recent.clear()" class="text-xs font-semibold text-slate">Clear</button></div>
                        <div class="mt-2 flex flex-wrap gap-2"><template x-for="t in $store.recent.terms" :key="t"><button type="button" @click="submit(t)" class="chip"><x-ico name="clock" :size="12" /><span x-text="t"></span></button></template></div>
                    </div>
                </template>
                <p class="text-xs font-bold uppercase tracking-wider text-mist">Popular searches</p>
                <div class="mt-2 flex flex-wrap gap-2"><template x-for="t in suggestions" :key="t"><button type="button" @click="submit(t)" class="chip" x-text="t"></button></template></div>
                <p class="mt-6 text-xs font-bold uppercase tracking-wider text-mist">Browse categories</p>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach(($navCategories ?? collect())->take(9) as $c)
                        <a href="{{ $c->url }}" class="rounded-xl border border-line p-2 text-center"><span class="block aspect-square overflow-hidden rounded-lg bg-sky">@if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif</span><span class="mt-1.5 block text-[0.6875rem] font-semibold leading-tight">{{ $c->name }}</span></a>
                    @endforeach
                </div>
            </div>
        </template>
        <template x-if="q.trim()">
            <div>
                <template x-if="loading && !results.products.length"><p class="p-4 text-sm text-slate">Searching…</p></template>
                <template x-if="!loading && !results.products.length && !results.categories.length"><p class="p-4 text-sm text-slate">No results for “<span x-text="q"></span>”.</p></template>
                <template x-if="results.categories.length"><div class="mb-2 flex flex-wrap gap-2"><template x-for="c in results.categories" :key="c.url"><a :href="c.url" class="chip"><x-ico name="grid" :size="12" /><span x-text="c.name"></span></a></template></div></template>
                <ul class="divide-y divide-line-soft">
                    <template x-for="p in results.products" :key="p.id">
                        <li><a :href="p.url" class="flex items-center gap-3 py-2.5"><img :src="p.images[0]" alt="" class="h-14 w-14 rounded-lg border border-line-soft bg-canvas object-cover"><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold" x-text="p.name"></span><span class="block text-xs text-slate" x-text="[p.brand, p.material].filter(Boolean).join(' · ')"></span></span><span class="price text-sm" x-text="p.price_formatted"></span></a></li>
                    </template>
                </ul>
                <button type="button" x-show="results.products.length" @click="submit()" class="btn btn-outline btn-block mt-3">See all results</button>
            </div>
        </template>
    </div>
</div>
