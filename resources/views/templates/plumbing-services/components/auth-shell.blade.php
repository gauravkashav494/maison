@props(['title', 'text' => null])
@php $p = tsetting('site'); $biz = template()->contact(); @endphp
<section class="ps-container py-6 lg:py-14">
    <div class="mx-auto grid max-w-4xl overflow-hidden rounded-3xl bg-white shadow-card ring-1 ring-line lg:grid-cols-2">
        <div class="band-deep relative hidden flex-col justify-between overflow-hidden p-8 lg:flex">
            <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-bright/40 blur-3xl"></div>
            <div class="relative flex items-center gap-2"><span class="grid h-9 w-9 place-items-center rounded-xl bg-white text-primary"><x-ico name="droplet" :size="20" :stroke="2" /></span><span class="font-display text-lg font-extrabold">{{ $p['logo_primary'] ?? 'Pipe' }}<span class="text-accent">{{ $p['logo_accent'] ?? 'Care' }}</span></span></div>
            <div class="relative">
                <p class="font-display text-2xl font-extrabold leading-tight">Your bookings, addresses and history in one place.</p>
                <ul class="mt-4 space-y-2 text-sm text-white/85">
                    <li class="flex items-center gap-2"><x-ico name="calendar" :size="16" class="text-accent" /> Track every service request</li>
                    <li class="flex items-center gap-2"><x-ico name="map-pin" :size="16" class="text-accent" /> Saved addresses for faster booking</li>
                    <li class="flex items-center gap-2"><x-ico name="shield" :size="16" class="text-accent" /> 30-day warranty on every job</li>
                </ul>
            </div>
            <p class="relative text-xs text-white/60">© {{ date('Y') }} {{ $biz['name'] }}</p>
        </div>
        <div class="p-6 sm:p-8">
            <h1 class="font-display text-2xl font-extrabold">{{ $title }}</h1>
            @if($text)<p class="mt-1 text-sm text-slate">{{ $text }}</p>@endif
            @if(session('status'))<p class="mt-4 rounded-xl bg-success-light px-4 py-3 text-sm font-semibold text-success">{{ session('status') }}</p>@endif
            <div class="mt-5">{{ $slot }}</div>
        </div>
    </div>
</section>