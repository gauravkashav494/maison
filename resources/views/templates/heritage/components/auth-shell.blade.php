@props(['title', 'text' => null])
<section class="h-container py-8 lg:py-14">
    <div class="mx-auto grid max-w-4xl overflow-hidden rounded-2xl border border-line bg-white lg:grid-cols-2">
        <div class="hidden flex-col justify-between bg-red p-8 text-white lg:flex">
            <div class="flex items-center gap-2"><span class="grid h-9 w-9 place-items-center rounded-xl bg-white/15"><x-ico name="leaf" :size="20" /></span><span class="text-lg font-semibold">{{ tsetting('site.logo_primary', 'Annapurna') }}{{ tsetting('site.logo_accent', 'Organics') }}</span></div>
            <div>
                <p class="text-2xl font-semibold leading-tight">Pure Indian pantry, delivered to your door.</p>
                <ul class="mt-4 space-y-2 text-sm text-white/85">
                    <li class="flex items-center gap-2"><x-ico name="bolt" :size="16" /> {{ tsetting('site.delivery_note', 'Pan-India delivery') }}</li>
                    <li class="flex items-center gap-2"><x-ico name="tag" :size="16" /> Member offers & one-tap pantry reorders</li>
                    <li class="flex items-center gap-2"><x-ico name="shield" :size="16" /> Quality promise on every order</li>
                </ul>
            </div>
            <p class="text-xs text-white/60">© {{ date('Y') }} {{ setting('site.name') }}</p>
        </div>
        <div class="p-6 sm:p-8">
            <h1 class="text-2xl font-semibold">{{ $title }}</h1>
            @if($text)<p class="mt-1 text-sm text-muted">{{ $text }}</p>@endif
            @if(session('status'))<p class="mt-4 rounded-xl bg-cream px-4 py-3 text-sm font-semibold text-maroon">{{ session('status') }}</p>@endif
            <div class="mt-5">{{ $slot }}</div>
        </div>
    </div>
</section>
