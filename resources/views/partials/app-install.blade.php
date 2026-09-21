{{-- "Add to home screen" card (phones, home page only): Chrome/Android once the browser allows it; iOS gets a share-sheet hint. --}}
@if(request()->routeIs('home'))
<div x-data x-show="$store.app.showInstall" x-cloak x-transition class="above-tabs px-3 pb-2 lg:hidden">
    <div class="flex items-center gap-3 border border-ink/10 bg-ivory p-3 shadow-[0_30px_60px_-30px_rgba(21,20,18,0.35)]">
        <img src="/templates/fashion/icon-192.png" alt="" class="h-11 w-11 shrink-0 rounded-xl">
        <div class="min-w-0 flex-1">
            <p class="font-serif text-base leading-tight">Add {{ template()->pwa()['name'] ?? ($site['name'] ?? config('app.name')) }} to your home screen</p>
            <p class="mt-0.5 text-[0.6875rem] leading-snug text-smoke" x-text="$store.app.ios ? 'Tap Share, then “Add to Home Screen”.' : 'Opens full screen with one tap — no browser bar.'"></p>
        </div>
        <button type="button" x-show="!$store.app.ios" @click="$store.app.install()" class="btn btn-primary btn-sm">Add</button>
        <button type="button" @click="$store.app.dismiss()" class="grid h-8 w-8 shrink-0 place-items-center text-smoke" aria-label="Not now"><x-ico name="close" :size="16" /></button>
    </div>
</div>
@endif
