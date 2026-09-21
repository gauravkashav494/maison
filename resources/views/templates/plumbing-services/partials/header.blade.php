{{-- Desktop header (lg+): top strip with hours/phone/emergency, logo, navigation with a services mega menu, Call + Book actions. Phones use partials/mobile-header. --}}
@php
    $p = tsetting('site');
    $header = $menus['header'] ?? collect();
    $servicesNav = $navServices ?? collect();
@endphp
<header x-data="header()" class="sticky top-0 z-40 hidden bg-white lg:block" :class="scrolled && 'shadow-card'">
    {{-- Top strip --}}
    <div class="bg-deep text-white">
        <div class="ps-container flex h-9 items-center justify-between gap-6 text-xs">
            <ul class="flex items-center gap-5">
                @foreach(array_slice($p['usp_strip'] ?? [], 0, 4) as $usp)
                    <li class="flex items-center gap-1.5 text-white/85 {{ $loop->index >= 2 ? 'hidden xl:flex' : '' }}"><x-ico name="check" :size="13" class="text-accent" /> {{ $usp }}</li>
                @endforeach
            </ul>
            <div class="flex items-center gap-5">
                @if($biz['hours'])<span class="hidden items-center gap-1.5 text-white/85 xl:flex"><x-ico name="clock" :size="13" /> {{ $biz['hours'] }}</span>@endif
                @if($biz['emergency_available'])<a href="{{ route('services.emergency') }}" class="flex items-center gap-1.5 font-bold text-white hover:text-accent"><span class="pulse-dot inline-block h-1.5 w-1.5 rounded-full bg-danger text-danger"></span> 24×7 emergency plumber</a>@endif
            </div>
        </div>
    </div>

    <div class="ps-container flex h-[4.5rem] items-center gap-3 2xl:gap-6">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ $biz['name'] }} home">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-primary text-white shadow-glow"><x-ico name="droplet" :size="24" :stroke="2" /></span>
            <span class="font-display text-[1.35rem] font-extrabold tracking-tight text-deep">{{ $p['logo_primary'] ?? 'Pipe' }}<span class="text-bright">{{ $p['logo_accent'] ?? 'Care' }}</span></span>
        </a>

        <nav class="relative flex min-w-0 flex-1 items-center justify-center gap-0 xl:gap-1" aria-label="Main">
            <div class="relative" @mouseenter="openMega()" @mouseleave="closeMega()">
                <a href="{{ route('services.index') }}" class="nav-link {{ request()->routeIs('services.*') ? 'is-active' : '' }}" :aria-expanded="mega">Services <x-ico name="chevron-down" :size="14" /></a>
                <div x-show="mega" x-cloak x-transition.opacity.duration.150ms class="mega left-1/2 w-[min(58rem,calc(100vw-2rem))] -translate-x-1/2">
                    <div class="grid grid-cols-[1fr_16rem] gap-6 rounded-3xl bg-white p-6 shadow-float ring-1 ring-line">
                        <div>
                            <div class="grid grid-cols-3 gap-1">
                                @foreach($servicesNav as $s)
                                    <a href="{{ $s->url }}" class="mega-link"><span class="svc-ico h-9 w-9 rounded-xl {{ $s->is_emergency ? 'svc-ico-danger' : '' }}"><x-ico :name="$s->icon ?: 'wrench'" :size="18" /></span><span class="leading-tight">{{ $s->name }}</span></a>
                                @endforeach
                            </div>
                            <a href="{{ route('services.index') }}" class="sec-link mt-3 inline-flex">All services <x-ico name="arrow-right" :size="14" /></a>
                        </div>
                        <div class="band-deep flex flex-col justify-between rounded-2xl p-5">
                            <div>
                                <p class="eyebrow text-white/80">Not sure what you need?</p>
                                <p class="mt-2 font-display text-lg font-extrabold leading-tight">Tell us the problem — we will send the right plumber.</p>
                            </div>
                            <div class="mt-4 space-y-2">
                                <a href="{{ route('booking.create') }}" class="btn btn-accent btn-sm btn-block">Book a plumber</a>
                                <a href="{{ $biz['phone_href'] }}" class="btn btn-glass btn-sm btn-block"><x-ico name="phone" :size="15" /> {{ $biz['phone'] }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @foreach($header as $item)
                @if($item->url !== '/services')
                    <a href="{{ $item->url }}" class="nav-link {{ $item->is_accent ? 'nav-link-accent' : '' }} {{ request()->is(ltrim($item->url, '/')) ? 'is-active' : '' }}" @if($item->opens_in_new_tab) target="_blank" rel="noopener" @endif>{{ $item->is_accent ? '' : '' }}{{ $item->label }}</a>
                @endif
            @endforeach
        </nav>

        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ $biz['phone_href'] }}" class="flex items-center gap-2.5 rounded-full p-1 hover:bg-sky 2xl:py-1.5 2xl:pl-1.5 2xl:pr-4" aria-label="Call {{ $biz['phone'] }}">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-sky text-primary"><x-ico name="phone" :size="18" /></span>
                <span class="hidden leading-tight 2xl:block"><span class="block text-[0.65rem] font-semibold uppercase tracking-wider text-slate">Call us</span><span class="block font-display text-sm font-extrabold text-deep">{{ $biz['phone'] }}</span></span>
            </a>
            @if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="icon-btn hidden h-11 w-11 bg-success-light text-whatsapp-dark hover:bg-success-light xl:grid" aria-label="WhatsApp"><x-ico name="whatsapp" :size="22" /></a>@endif
            <a href="{{ route('booking.create') }}" class="btn btn-primary px-4 2xl:px-5">Book a plumber</a>
            @auth
                <a href="{{ route('account.index') }}" class="icon-btn hidden h-11 w-11 2xl:grid" aria-label="My account"><x-ico name="user" :size="22" /></a>
            @else
                <a href="{{ route('login') }}" class="icon-btn hidden h-11 w-11 2xl:grid" aria-label="Log in"><x-ico name="user" :size="22" /></a>
            @endauth
        </div>
    </div>
</header>
