@php $headerItems = $menus['header']; @endphp
<div x-data="{ section: {{ $headerItems->first(fn ($i) => $i->children->isNotEmpty())?->id ?? 'null' }} }" x-cloak x-show="$store.ui.menuOpen" class="fixed inset-0 z-[70] lg:hidden"
     x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <button type="button" aria-label="Close menu" class="absolute inset-0 bg-ink/40" @click="$store.ui.closeAll()"></button>
    <aside x-show="$store.ui.menuOpen" x-trap.noscroll="$store.ui.menuOpen"
           x-transition:enter="transition duration-500 ease-[var(--ease-luxe)]" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition duration-400 ease-[var(--ease-luxe)]" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
           class="absolute inset-y-0 left-0 flex w-[88%] max-w-md flex-col bg-ivory text-ink" role="dialog" aria-modal="true" aria-label="Menu">
        <div class="flex h-[4.5rem] items-center justify-between border-b border-ink/10 px-5">
            <span class="font-serif text-xl tracking-[0.12em]">{{ $site['logo_primary'] ?? 'MAISON' }} <span class="italic font-light">{{ $site['logo_accent'] ?? 'Élan' }}</span></span>
            <button type="button" @click="$store.ui.closeAll()" aria-label="Close" class="-mr-2 grid h-10 w-10 place-items-center"><x-ico name="close" :size="20" /></button>
        </div>

        <div class="flex-1 overflow-y-auto px-5">
            @foreach($headerItems as $item)
                @if($item->children->isNotEmpty())
                    @php
                        $groups = $item->children->groupBy(fn ($c) => $c->group ?: 'Links');
                        $tiles = $groups->pull('Tiles', collect());
                    @endphp
                    <div class="border-b border-ink/10">
                        <button type="button" @click="section = section === {{ $item->id }} ? null : {{ $item->id }}" :aria-expanded="section === {{ $item->id }}" class="flex w-full items-center justify-between py-5 font-serif text-2xl">
                            {{ $item->label }}
                            <x-ico name="plus" :size="16" x-show="section !== {{ $item->id }}" />
                            <x-ico name="minus" :size="16" x-show="section === {{ $item->id }}" x-cloak />
                        </button>
                        <div x-show="section === {{ $item->id }}" x-collapse x-cloak>
                            <div class="space-y-6 pb-6">
                                @foreach($groups as $title => $links)
                                    <div>
                                        <p class="eyebrow mb-3 text-[0.5625rem] text-taupe">{{ $title }}</p>
                                        <ul class="grid grid-cols-2 gap-x-4 gap-y-2.5">
                                            @foreach($links as $link)
                                                <li><a href="{{ $link->href }}" class="text-sm {{ $link->is_accent ? 'text-rouge' : '' }}">{{ $link->label }}</a></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                                @if($tiles->isNotEmpty())
                                    <div class="grid grid-cols-2 gap-3">
                                        @foreach($tiles->take(2) as $tile)
                                            <a href="{{ $tile->href }}" class="relative block aspect-[4/3] overflow-hidden bg-sand">
                                                @if($tile->image_url)<img src="{{ $tile->image_url }}" alt="" loading="lazy" class="img-cover">@endif
                                                <div class="absolute inset-0 bg-gradient-to-t from-ink/60 to-transparent"></div>
                                                <span class="absolute bottom-3 left-3 font-serif text-lg text-ivory">{{ $tile->label }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ $item->href }}" class="block border-b border-ink/10 py-5 font-serif text-2xl">{{ $item->label }}</a>
                @endif
            @endforeach

            <ul class="space-y-4 py-6 text-sm">
                <li><a href="{{ auth()->check() ? route('account.index') : route('login') }}" class="flex items-center gap-3"><x-ico name="user" :size="16" /> Account</a></li>
                <li><a href="{{ route('account.wishlist') }}" class="flex items-center gap-3"><x-ico name="heart" :size="16" /> Wishlist</a></li>
                <li><a href="/stores" class="flex items-center gap-3"><x-ico name="pin" :size="16" /> Store locator</a></li>
            </ul>
        </div>

        <div class="border-t border-ink/10 px-5 py-4 text-[0.6875rem] text-smoke">
            <p>{{ $site['contact_email'] ?? '' }}</p>
            <p class="mt-1">{{ $site['contact_hours'] ?? '' }}</p>
        </div>
    </aside>
</div>
