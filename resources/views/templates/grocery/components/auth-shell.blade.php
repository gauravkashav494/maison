@props(['title', 'text' => null])
<section class="g-container py-8 lg:py-14">
    <div class="mx-auto grid max-w-4xl overflow-hidden rounded-2xl border border-line bg-white lg:grid-cols-2">
        <div class="hidden flex-col justify-between bg-leaf p-8 text-white lg:flex">
            <div class="flex items-center gap-2"><span class="grid h-9 w-9 place-items-center rounded-xl bg-white/15"><x-ico name="leaf" :size="20" /></span><span class="text-lg font-extrabold">{{ tsetting('site.logo_primary', 'Maison') }}{{ tsetting('site.logo_accent', 'Fresh') }}</span></div>
            <div>
                <p class="text-2xl font-extrabold leading-tight">Groceries in minutes, every day.</p>
                <ul class="mt-4 space-y-2 text-sm text-white/85">
                    <li class="flex items-center gap-2"><x-ico name="bolt" :size="16" /> {{ tsetting('site.delivery_promise', 'Superfast delivery') }}</li>
                    <li class="flex items-center gap-2"><x-ico name="tag" :size="16" /> Member-only deals & one-tap reorders</li>
                    <li class="flex items-center gap-2"><x-ico name="shield" :size="16" /> Fresh guarantee on every order</li>
                </ul>
            </div>
            <p class="text-xs text-white/60">© {{ date('Y') }} {{ setting('site.name') }}</p>
        </div>
        <div class="p-6 sm:p-8">
            <h1 class="text-2xl font-extrabold">{{ $title }}</h1>
            @if($text)<p class="mt-1 text-sm text-slate">{{ $text }}</p>@endif
            @if(session('status'))<p class="mt-4 rounded-xl bg-leaf-light px-4 py-3 text-sm font-semibold text-leaf-dark">{{ session('status') }}</p>@endif
            <div class="mt-5">{{ $slot }}</div>
        </div>
    </div>
</section>
