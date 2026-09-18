{{-- "Add to home screen" card (phones, home page only): Chrome/Android once the browser allows installing; iOS gets a share-sheet hint. --}}
@if(request()->routeIs('home'))
<div x-data x-show="$store.app.showInstall" x-cloak x-transition class="above-tabs px-3 pb-2 lg:hidden">
    <div class="flex items-center gap-3 rounded-2xl border border-line bg-white p-3 shadow-float">
        <img src="/templates/grocery/icon-192.png" alt="" class="h-11 w-11 shrink-0 rounded-xl">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-extrabold leading-tight">Get the {{ template()->pwa()['name'] ?? ($site['name'] ?? config('app.name')) }} app</p>
            <p class="mt-0.5 text-xs leading-snug text-slate" x-text="$store.app.ios ? 'Tap Share, then “Add to Home Screen”.' : 'Full screen, faster checkout, always one tap away.'"></p>
        </div>
        <button type="button" x-show="!$store.app.ios" @click="$store.app.install()" class="btn btn-primary btn-sm">Install</button>
        <button type="button" @click="$store.app.dismiss()" class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-slate hover:bg-paper" aria-label="Not now"><x-ico name="close" :size="16" /></button>
    </div>
</div>
@endif
