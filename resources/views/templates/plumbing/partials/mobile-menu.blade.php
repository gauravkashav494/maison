{{-- Mobile drawer: categories with expandable sub-categories, quick links, account, support --}}
@php $p = tsetting('site'); $cats = $navCategories ?? collect(); $links = $menus['header'] ?? collect(); $phone = $p['support_phone'] ?? null; @endphp
<div x-data="{ openCat: null }" x-show="$store.ui.menuOpen" x-cloak>
    <div class="overlay" x-show="$store.ui.menuOpen" x-transition.opacity @click="$store.ui.closeAll()"></div>
    <aside class="sheet inset-y-0 left-0 flex w-[88%] max-w-sm flex-col" x-show="$store.ui.menuOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" x-trap.noscroll="$store.ui.menuOpen" aria-label="Menu">
        <div class="flex items-center justify-between bg-deep px-4 py-3 text-white">
            <a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-white/15"><x-ico name="user" :size="20" /></span>
                <span class="leading-tight"><span class="block text-sm font-bold">{{ auth()->check() ? 'Hi, '.Str::of(auth()->user()->name)->before(' ') : 'Login / Sign up' }}</span><span class="block text-xs text-white/70">{{ auth()->check() ? 'My account & orders' : 'Track orders, save addresses' }}</span></span>
            </a>
            <button type="button" @click="$store.ui.closeAll()" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-white/10" aria-label="Close"><x-ico name="close" :size="20" /></button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <p class="px-4 pb-1 pt-4 text-xs font-bold uppercase tracking-wider text-mist">Shop by category</p>
            <ul>
                @foreach($cats as $c)
                    <li class="border-b border-line-soft">
                        <div class="flex items-center">
                            <a href="{{ $c->url }}" class="flex flex-1 items-center gap-3 px-4 py-3">
                                <span class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-sky">@if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif</span>
                                <span class="text-sm font-semibold">{{ $c->name }}</span>
                            </a>
                            @if($c->children->isNotEmpty())
                                <button type="button" @click="openCat = openCat === {{ $c->id }} ? null : {{ $c->id }}" class="grid h-12 w-12 place-items-center text-slate" :aria-expanded="openCat === {{ $c->id }}" aria-label="Show sub-categories"><x-ico name="chevron-down" :size="18" class="transition-transform" ::class="openCat === {{ $c->id }} && 'rotate-180'" /></button>
                            @endif
                        </div>
                        @if($c->children->isNotEmpty())
                            <ul x-show="openCat === {{ $c->id }}" x-collapse x-cloak class="bg-canvas pb-2">
                                @foreach($c->children as $sub)<li><a href="{{ $sub->url }}" class="block py-2 pl-[4.25rem] pr-4 text-sm text-slate hover:text-primary">{{ $sub->name }}</a></li>@endforeach
                                <li><a href="{{ $c->url }}" class="block py-2 pl-[4.25rem] pr-4 text-sm font-bold text-primary">All {{ Str::lower($c->name) }} →</a></li>
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if($links->isNotEmpty())
                <p class="px-4 pb-1 pt-4 text-xs font-bold uppercase tracking-wider text-mist">Quick links</p>
                <ul class="px-2">
                    @foreach($links as $item)<li><a href="{{ $item->href }}" class="flex items-center gap-2 rounded-lg px-2 py-2.5 text-sm font-semibold {{ $item->is_accent ? 'text-accent-dark' : '' }}">@if($item->is_accent)<x-ico name="percent" :size="16" />@endif{{ $item->label }}</a></li>@endforeach
                </ul>
            @endif

            <div class="m-4 rounded-xl bg-sky p-4">
                <p class="text-sm font-bold text-deep">Need help choosing?</p>
                <p class="mt-0.5 text-xs text-slate">Talk to a plumbing expert{{ !empty($p['support_hours']) ? ' · '.$p['support_hours'] : '' }}</p>
                <div class="mt-3 flex gap-2">
                    @if($phone)<a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="btn btn-primary btn-sm flex-1"><x-ico name="phone" :size="14" /> Call</a>@endif
                    @if(!empty($p['whatsapp_number']))<a href="https://wa.me/{{ preg_replace('/\D/', '', $p['whatsapp_number']) }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm flex-1"><x-ico name="whatsapp" :size="14" /> WhatsApp</a>@endif
                </div>
            </div>
        </div>

        <div class="border-t border-line p-3 safe-bottom">
            @auth
                <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-ghost btn-block"><x-ico name="logout" :size="16" /> Log out</button></form>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary btn-block">Login / Sign up</a>
            @endauth
        </div>
    </aside>
</div>
