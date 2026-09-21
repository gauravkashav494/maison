{{-- Desktop inline search with live results panel. Search is the primary way plumbing buyers shop, so it is wide and always visible. --}}
@php $p = tsetting('site'); @endphp
<div x-data="search(@js($p['search_suggestions'] ?? []))" class="relative" @click.outside="focused = false" @keydown.escape="focused = false">
    <form @submit.prevent="submit()" role="search" class="flex h-12 overflow-hidden rounded-xl border-2 border-primary bg-white focus-within:shadow-[0_0_0_4px_rgba(25,118,210,.15)]">
        <x-ico name="search" :size="20" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate" />
        <input x-ref="input" x-model="q" @focus="focused = true" type="search" :placeholder="placeholder" autocomplete="off" aria-label="Search products" class="min-w-0 flex-1 bg-transparent pl-12 pr-3 text-[0.95rem] text-ink outline-none placeholder:text-mist">
        <button type="submit" class="shrink-0 bg-primary px-6 text-sm font-bold text-white hover:bg-deep">Search</button>
    </form>

    <div x-show="showPanel" x-cloak x-transition.opacity.duration.120ms class="absolute inset-x-0 top-full z-40 mt-2 max-h-[70vh] overflow-y-auto rounded-xl border border-line bg-white p-2 shadow-float">
        <template x-if="!q.trim()">
            <div class="p-2">
                <template x-if="$store.recent.terms.length">
                    <div class="mb-3">
                        <div class="flex items-center justify-between px-1"><p class="text-xs font-bold uppercase tracking-wider text-mist">Recent</p><button type="button" @click="$store.recent.clear()" class="text-xs font-semibold text-slate hover:text-ink">Clear</button></div>
                        <div class="mt-2 flex flex-wrap gap-2"><template x-for="t in $store.recent.terms" :key="t"><button type="button" @click="submit(t)" class="chip"><x-ico name="clock" :size="12" /><span x-text="t"></span></button></template></div>
                    </div>
                </template>
                <p class="px-1 text-xs font-bold uppercase tracking-wider text-mist">Popular searches</p>
                <div class="mt-2 flex flex-wrap gap-2"><template x-for="t in suggestions" :key="t"><button type="button" @click="submit(t)" class="chip" x-text="t"></button></template></div>
            </div>
        </template>
        <template x-if="q.trim()">
            <div>
                <template x-if="loading && !results.products.length"><p class="p-4 text-sm text-slate">Searching…</p></template>
                <template x-if="!loading && !results.products.length && !results.categories.length"><p class="p-4 text-sm text-slate">No results for “<span x-text="q"></span>”. Try a size (1 inch), a material (CPVC) or a brand.</p></template>
                <template x-if="results.categories.length">
                    <div class="flex flex-wrap gap-2 p-2"><template x-for="c in results.categories" :key="c.url"><a :href="c.url" class="chip"><x-ico name="grid" :size="12" /><span x-text="c.name"></span></a></template></div>
                </template>
                <ul class="divide-y divide-line-soft">
                    <template x-for="p in results.products" :key="p.id">
                        <li>
                            <a :href="p.url" class="flex items-center gap-3 rounded-lg p-2 hover:bg-sky/60">
                                <img :src="p.images[0]" alt="" class="h-12 w-12 rounded-lg border border-line-soft bg-canvas object-cover">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold" x-text="p.name"></span>
                                    <span class="block text-xs text-slate" x-text="[p.brand, p.material, p.sizes.length > 1 ? p.sizes.length + ' sizes' : p.sizes[0]].filter(Boolean).join(' · ')"></span>
                                </span>
                                <span class="price text-sm" x-text="p.price_formatted"></span>
                            </a>
                        </li>
                    </template>
                </ul>
                <button type="button" x-show="results.products.length" @click="submit()" class="mt-1 w-full rounded-lg p-2.5 text-center text-sm font-bold text-primary hover:bg-sky">See all results for “<span x-text="q"></span>”</button>
            </div>
        </template>
    </div>
</div>
