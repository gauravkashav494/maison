{{-- Full-screen search (mobile) --}}
@php $g = tsetting('site'); @endphp
<div x-data="search(@js($g['search_suggestions'] ?? []))" x-show="$store.ui.searchOpen" x-cloak class="fixed inset-0 z-[80] flex flex-col bg-white lg:hidden" x-trap.noscroll="$store.ui.searchOpen">
    <form @submit.prevent="submit()" role="search" class="flex items-center gap-2 border-b border-line px-3 py-2">
        <button type="button" @click="$store.ui.closeAll()" class="grid h-10 w-10 place-items-center rounded-lg hover:bg-paper" aria-label="Back"><x-ico name="arrow-left" :size="20" /></button>
        <div class="relative flex-1">
            <x-ico name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate" />
            <input x-ref="input" x-model="q" type="search" :placeholder="placeholder" autocomplete="off" aria-label="Search products" class="field h-11 pl-10">
        </div>
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
            </div>
        </template>
        <template x-if="q.trim()">
            <div>
                <template x-if="!loading && !results.products.length && !results.categories.length"><p class="p-4 text-center text-sm text-slate">No results for “<span x-text="q"></span>”.</p></template>
                <div class="flex flex-wrap gap-2" x-show="results.categories.length"><template x-for="c in results.categories" :key="c.url"><a :href="c.url" class="chip" x-text="c.name"></a></template></div>
                <ul class="mt-2 divide-y divide-line">
                    <template x-for="p in results.products" :key="p.id">
                        <li><a :href="p.url" class="flex items-center gap-3 py-2.5">
                            <img :src="p.images[0]" alt="" class="h-14 w-14 rounded-lg bg-paper object-cover">
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold" x-text="p.name"></span><span class="block text-xs text-slate" x-text="p.unit || p.brand"></span></span>
                            <span class="text-sm font-bold tabular" x-text="p.price_formatted"></span>
                        </a></li>
                    </template>
                </ul>
                <button type="button" x-show="results.products.length" @click="submit()" class="btn btn-outline btn-block mt-3">See all results</button>
            </div>
        </template>
    </div>
</div>
