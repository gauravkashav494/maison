{{-- Full-screen search (phones/tablets): live service and problem suggestions --}}
@php $p = tsetting('site'); $suggestions = array_values($p['search_suggestions'] ?? []); @endphp
<div x-data="search(@js($suggestions), @js($p['search_placeholder'] ?? 'Search plumbing services'))" x-show="$store.ui.searchOpen" x-cloak x-init="$watch('$store.ui.searchOpen', (v) => { if (v) { open(); $nextTick(() => $refs.input?.focus()); } })" class="fixed inset-0 z-[80] flex flex-col bg-white lg:hidden" x-trap.noscroll="$store.ui.searchOpen" role="dialog" aria-modal="true" aria-label="Search">
    <form action="{{ route('services.index') }}" method="get" @submit.prevent="submit()" class="app-bar flex items-center gap-2 border-b border-line-soft px-2 py-2">
        <button type="button" @click="$store.ui.searchOpen = false" class="icon-btn" aria-label="Close search"><x-ico name="arrow-left" :size="22" /></button>
        <div class="flex h-11 flex-1 items-center gap-2 rounded-full bg-canvas px-4 ring-1 ring-line">
            <x-ico name="search" :size="18" class="text-primary" />
            <input x-ref="input" type="search" name="q" x-model="q" :placeholder="placeholder" enterkeyhint="search" autocomplete="off" class="min-w-0 flex-1 bg-transparent text-[0.95rem] focus:outline-none" aria-label="Search plumbing services">
            <button type="button" x-show="q" @click="q = ''" class="text-mist" aria-label="Clear"><x-ico name="close" :size="16" /></button>
        </div>
    </form>
    <div class="flex-1 overflow-y-auto px-4 pb-24">
        <template x-if="!q">
            <div class="pt-4">
                <template x-if="$store.recent.terms.length">
                    <div>
                        <div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-mist">Recent</p><button type="button" @click="$store.recent.clear()" class="text-xs font-semibold text-slate">Clear</button></div>
                        <div class="mt-2 flex flex-wrap gap-2"><template x-for="t in $store.recent.terms" :key="t"><button type="button" @click="pick(t)" class="chip"><x-ico name="clock" :size="13" /> <span x-text="t"></span></button></template></div>
                    </div>
                </template>
                <p class="mt-4 text-xs font-bold uppercase tracking-wider text-mist">Popular searches</p>
                <div class="mt-2 flex flex-wrap gap-2"><template x-for="t in examples" :key="t"><button type="button" @click="pick(t)" class="chip" x-text="t"></button></template></div>
            </div>
        </template>
        <div class="pt-3" x-show="results.services.length || results.problems.length">
            <template x-if="results.problems.length">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-mist" x-text="q ? 'Matching problems' : 'Common problems'"></p>
                    <div class="mt-2 flex flex-wrap gap-2"><template x-for="p in results.problems" :key="p.label"><a :href="p.url" class="chip" x-text="p.label"></a></template></div>
                </div>
            </template>
            <p class="mt-4 text-xs font-bold uppercase tracking-wider text-mist" x-text="q ? 'Services' : 'All services'"></p>
            <ul class="mt-2 divide-y divide-line-soft">
                <template x-for="s in results.services" :key="s.url">
                    <li>
                        <div class="flex items-center gap-3 py-3">
                            <a :href="s.url" class="svc-ico h-11 w-11" :class="s.is_emergency && 'svc-ico-danger'"><x-ico name="wrench" :size="20" /></a>
                            <a :href="s.url" class="min-w-0 flex-1"><span class="block font-display text-sm font-extrabold" x-text="s.name"></span><span class="line-clamp-1 text-xs text-slate" x-text="s.excerpt"></span></a>
                            <a :href="s.book_url" class="btn btn-soft btn-sm">Book</a>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
        <div x-show="empty" x-cloak class="pt-10 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-sky text-primary"><x-ico name="search" :size="24" /></span>
            <p class="mt-3 font-display font-bold">No matching service</p>
            <p class="mt-1 text-sm text-slate">Describe the problem and we will send the right plumber.</p>
            <a :href="'/book?problem=' + encodeURIComponent(q)" class="btn btn-primary mt-4">Book with this problem</a>
        </div>
    </div>
</div>