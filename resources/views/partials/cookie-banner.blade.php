<div x-data="{ custom: false, analytics: false, marketing: false }" x-cloak x-show="!$store.cookies.decided"
     x-transition:enter="transition duration-500 ease-[var(--ease-luxe)]" x-transition:enter-start="translate-y-6 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
     class="fixed inset-x-4 bottom-20 z-[60] mx-auto max-w-xl border border-ink/10 bg-ivory p-5 shadow-[0_30px_60px_-30px_rgba(21,20,18,0.35)] lg:inset-x-auto lg:bottom-6 lg:right-6 lg:p-6"
     role="dialog" aria-label="Cookie preferences">
    <p class="font-serif text-xl">Cookies, briefly.</p>
    <p class="mt-2 text-[0.8125rem] leading-relaxed text-smoke">We use essential cookies to run the site and, with your consent, analytics and marketing cookies to improve it. <a href="/cookies" class="underline underline-offset-4">Cookie policy</a></p>
    <div x-show="custom" x-collapse x-cloak>
        <div class="mt-4 space-y-2 text-sm">
            <label class="flex items-center justify-between"><span>Essential</span><span class="text-[0.625rem] uppercase tracking-[0.2em] text-taupe">Required</span></label>
            <label class="flex cursor-pointer items-center justify-between"><span>Analytics</span><input type="checkbox" x-model="analytics" class="h-4 w-4 accent-ink"></label>
            <label class="flex cursor-pointer items-center justify-between"><span>Marketing</span><input type="checkbox" x-model="marketing" class="h-4 w-4 accent-ink"></label>
        </div>
    </div>
    <div class="mt-5 flex flex-wrap gap-2">
        <button type="button" @click="$store.cookies.acceptAll()" class="btn btn-primary btn-sm">Accept all</button>
        <button type="button" x-show="!custom" @click="custom = true" class="btn btn-outline btn-sm">Customise</button>
        <button type="button" x-show="custom" x-cloak @click="$store.cookies.save(analytics, marketing)" class="btn btn-outline btn-sm">Save choices</button>
        <button type="button" @click="$store.cookies.rejectAll()" class="btn btn-sm text-smoke hover:text-ink">Essential only</button>
    </div>
</div>
