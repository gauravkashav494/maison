{{--
    Cookie consent in the store's own style. Same choices and the same shared consent store
    ($store.cookies) as every template, so a decision is saved and honoured exactly as before.
--}}
<div x-data="{ custom: false, analytics: false, marketing: false }" x-cloak x-show="!$store.cookies.decided"
     x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
     class="bk-card fixed inset-x-3 bottom-3 z-[60] mx-auto max-w-md p-5 shadow-[0_18px_40px_rgb(15_30_25/.18)] sm:inset-x-auto sm:bottom-5 sm:right-5"
     role="dialog" aria-label="Cookie preferences">
    <div class="flex gap-3">
        <span class="bk-icon-tile !size-10 shrink-0"><x-heroicon-o-shield-check aria-hidden="true" /></span>
        <div>
            <p class="font-extrabold">Cookies on {{ setting('site.name', config('app.name')) }}</p>
            <p class="bk-muted mt-1 text-sm leading-relaxed">We use essential cookies to keep your order and sign-in working, and — only if you agree — analytics and marketing cookies to improve the store. <a href="/cookies" class="bk-link">Cookie policy</a></p>
        </div>
    </div>

    <div x-show="custom" x-collapse x-cloak>
        <div class="mt-4 divide-y divide-[var(--bk-line)] rounded-lg border border-[var(--bk-line)] text-sm">
            <p class="flex items-center justify-between px-3 py-2.5"><span class="font-semibold">Essential</span><span class="bk-badge bk-badge-ok">Always on</span></p>
            <label class="flex cursor-pointer items-center justify-between px-3 py-2.5"><span class="font-semibold">Analytics</span><input type="checkbox" x-model="analytics" class="size-4 accent-[var(--bk-brand)]"></label>
            <label class="flex cursor-pointer items-center justify-between px-3 py-2.5"><span class="font-semibold">Marketing</span><input type="checkbox" x-model="marketing" class="size-4 accent-[var(--bk-brand)]"></label>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button type="button" @click="$store.cookies.acceptAll()" class="bk-btn bk-btn-primary !py-2">Accept all</button>
        <button type="button" x-show="!custom" @click="custom = true" class="bk-btn bk-btn-outline !py-2">Customise</button>
        <button type="button" x-show="custom" x-cloak @click="$store.cookies.save(analytics, marketing)" class="bk-btn bk-btn-outline !py-2">Save choices</button>
        <button type="button" @click="$store.cookies.rejectAll()" class="bk-btn bk-btn-ghost !py-2 text-sm">Essential only</button>
    </div>
</div>
