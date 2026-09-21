@props(['title', 'text' => null])
@php $p = tsetting('site'); @endphp
<section class="p-container py-8 lg:py-14">
    <div class="mx-auto grid max-w-4xl overflow-hidden rounded-2xl border border-line bg-white lg:grid-cols-2">
        <div class="relative hidden flex-col justify-between overflow-hidden bg-deep p-8 text-white lg:flex">
            <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-bright/30 blur-3xl"></div>
            <div class="absolute -bottom-20 -left-10 h-56 w-56 rounded-full bg-accent/20 blur-3xl"></div>
            <div class="relative flex items-center gap-2"><span class="grid h-9 w-9 place-items-center rounded-xl bg-white text-deep"><x-ico name="droplet" :size="20" :stroke="2" /></span><span class="font-display text-lg font-extrabold">{{ $p['logo_primary'] ?? 'Plumb' }}<span class="text-accent">{{ $p['logo_accent'] ?? 'Kart' }}</span></span></div>
            <div class="relative">
                <p class="font-display text-2xl font-bold leading-tight">Everything for every plumbing job.</p>
                <ul class="mt-4 space-y-2 text-sm text-white/85">
                    <li class="flex items-center gap-2"><x-ico name="truck" :size="16" class="text-accent" /> {{ $p['delivery_promise'] ?? 'Pan-India delivery' }}</li>
                    <li class="flex items-center gap-2"><x-ico name="badge" :size="16" class="text-accent" /> Genuine brands with warranty</li>
                    <li class="flex items-center gap-2"><x-ico name="receipt" :size="16" class="text-accent" /> GST invoice on every order</li>
                </ul>
            </div>
            <p class="relative text-xs text-white/60">© {{ date('Y') }} {{ setting('site.name') }}</p>
        </div>
        <div class="p-6 sm:p-8">
            <h1 class="font-display text-2xl font-bold">{{ $title }}</h1>
            @if($text)<p class="mt-1 text-sm text-slate">{{ $text }}</p>@endif
            @if(session('status'))<p class="mt-4 rounded-xl bg-success-light px-4 py-3 text-sm font-semibold text-success">{{ session('status') }}</p>@endif
            <div class="mt-5">{{ $slot }}</div>
        </div>
    </div>
</section>
