@php $h = tsetting('site'); @endphp
<div x-data="search(@js($h['search_suggestions'] ?? []))" class="relative" @click.outside="open = false" @keydown.escape="open = false">
    <form @submit.prevent="submit()" role="search" class="relative">
        <input x-ref="input" x-model="q" @focus="open = true" type="search" placeholder="{{ $h['search_placeholder'] ?? 'Search' }}" autocomplete="off" aria-label="Search products" class="field h-12 rounded-full border-transparent bg-cream-dark pl-5 pr-14 text-[0.9375rem] focus:border-gold focus:bg-white">
        <button type="submit" class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full text-ink hover:bg-white" aria-label="Search"><x-ico name="search" :size="19" /></button>
    </form>
    <div x-show="open && (q.trim() || $store.recent.terms.length || suggestions.length)" x-cloak x-transition.opacity.duration.120ms class="absolute inset-x-0 top-full z-40 mt-2 max-h-[70vh] overflow-y-auto rounded-xl border border-line bg-warm p-2 shadow-float">
        <template x-if="!q.trim()">
            <div class="p-2">
                <template x-if="$store.recent.terms.length">
                    <div class="mb-3">
                        <div class="flex items-center justify-between px-1"><p class="eyebrow !text-[0.625rem]">Recent</p><button type="button" @click="$store.recent.clear()" class="text-xs font-medium text-muted hover:text-red">Clear</button></div>
                        <div class="mt-2 flex flex-wrap gap-2"><template x-for="t in $store.recent.terms" :key="t"><button type="button" @click="submit(t)" class="chip"><x-ico name="clock" :size="12" /><span x-text="t"></span></button></template></div>
                    </div>
                </template>
                <p class="eyebrow !text-[0.625rem] px-1">Popular</p>
                <div class="mt-2 flex flex-wrap gap-2"><template x-for="t in suggestions" :key="t"><button type="button" @click="submit(t)" class="chip" x-text="t"></button></template></div>
            </div>
        </template>
        <template x-if="q.trim()">
            <div>
                <template x-if="!loading && !results.products.length && !results.categories.length"><p class="p-4 text-sm text-muted">No results for “<span x-text="q"></span>”.</p></template>
                <div class="flex flex-wrap gap-2 p-2" x-show="results.categories.length"><template x-for="c in results.categories" :key="c.url"><a :href="c.url" class="chip"><x-ico name="grid" :size="12" /><span x-text="c.name"></span></a></template></div>
                <ul class="divide-y divide-line-soft">
                    <template x-for="p in results.products" :key="p.id">
                        <li><a :href="p.url" class="flex items-center gap-3 rounded-lg p-2 hover:bg-cream"><img :src="p.images[0]" alt="" class="h-12 w-12 rounded-md bg-cream object-cover"><span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium" x-text="p.name"></span><span class="block text-xs text-muted" x-text="[p.brand, p.unit].filter(Boolean).join(' · ')"></span></span><span class="text-sm font-semibold tabular" x-text="p.price_formatted"></span></a></li>
                    </template>
                </ul>
                <button type="button" x-show="results.products.length" @click="submit()" class="mt-1 w-full rounded-lg p-2.5 text-center text-sm font-semibold text-red hover:bg-cream">See all results for “<span x-text="q"></span>”</button>
            </div>
        </template>
    </div>
</div>
