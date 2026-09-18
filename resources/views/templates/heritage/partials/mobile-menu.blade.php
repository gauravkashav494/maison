{{-- Mobile drawer: category tree (accordion) + quick links + account --}}
@php $cats = $navCategories ?? collect(); $links = $menus['header'] ?? collect(); $help = $menus['footer_help'] ?? collect(); $h = tsetting('site'); @endphp
<div x-data x-show="$store.ui.menuOpen" x-cloak>
    <div class="overlay" x-show="$store.ui.menuOpen" x-transition.opacity @click="$store.ui.closeAll()"></div>
    <div class="sheet sheet-left" x-show="$store.ui.menuOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" x-trap.noscroll="$store.ui.menuOpen" x-data="accordion()">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <span class="font-serif text-xl"><span class="font-semibold text-red">{{ $h['logo_primary'] ?? 'Annapurna' }}</span> <span class="italic text-gold">{{ $h['logo_accent'] ?? 'Organics' }}</span></span>
            <button type="button" @click="$store.ui.closeAll()" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-cream" aria-label="Close"><x-ico name="close" :size="20" /></button>
        </div>
        <div class="flex-1 overflow-y-auto">
            <p class="eyebrow px-5 pt-4">Shop by category</p>
            <ul class="mt-2 divide-y divide-line-soft">
                @foreach($cats as $c)
                    <li>
                        <div class="flex items-center">
                            <a href="{{ $c->url }}" class="flex flex-1 items-center gap-3 px-5 py-3">
                                <span class="h-10 w-10 shrink-0 overflow-hidden rounded-full border border-line bg-cream">@if($c->image_url)<img src="{{ $c->image_url }}" alt="" class="img-cover" loading="lazy">@endif</span>
                                <span class="text-sm font-semibold">{{ $c->name }}</span>
                            </a>
                            @if($c->children->isNotEmpty())<button type="button" @click="toggle({{ $c->id }})" class="grid h-11 w-12 place-items-center text-muted" aria-label="Show sub-categories"><x-ico name="chevron-down" :size="18" class="transition-transform" ::class="open === {{ $c->id }} && 'rotate-180'" /></button>@endif
                        </div>
                        @if($c->children->isNotEmpty())
                            <ul x-show="open === {{ $c->id }}" x-collapse x-cloak class="mb-2 ml-[4.5rem] space-y-0.5 pb-1">
                                @foreach($c->children as $sub)<li><a href="{{ $sub->url }}" class="block rounded-md px-2 py-1.5 text-sm text-muted hover:text-red">{{ $sub->name }}</a></li>@endforeach
                                <li><a href="{{ $c->url }}" class="block px-2 py-1.5 text-sm font-semibold text-red">View all →</a></li>
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if($links->isNotEmpty())
                <ul class="mt-4 border-t border-line px-5 py-3">
                    @foreach($links as $item)<li><a href="{{ $item->href }}" class="block py-2 text-sm font-semibold {{ $item->is_accent ? 'text-red' : '' }}">{{ $item->label }}</a></li>@endforeach
                </ul>
            @endif
            @if($help->isNotEmpty())
                <p class="eyebrow px-5 pt-3">Help</p>
                <ul class="px-5 py-2">@foreach($help as $item)<li><a href="{{ $item->href }}" class="block py-1.5 text-sm text-muted">{{ $item->label }}</a></li>@endforeach</ul>
            @endif
        </div>
        <div class="border-t border-line p-4 safe-bottom">
            @auth<a href="{{ route('account.index') }}" class="btn btn-outline btn-block">My account</a>@else<a href="{{ route('login') }}" class="btn btn-primary btn-block">Login / Sign up</a>@endauth
        </div>
    </div>
</div>
