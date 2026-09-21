{{-- Slide-in menu (opened from the services page header / footer on phones) --}}
@php $servicesNav = $navServices ?? collect(); @endphp
<div x-data x-show="$store.ui.menuOpen" x-cloak class="lg:hidden">
    <div class="overlay" x-show="$store.ui.menuOpen" x-transition.opacity @click="$store.ui.menuOpen = false"></div>
    <aside class="sheet inset-y-0 left-0 flex w-[86vw] max-w-sm flex-col" x-show="$store.ui.menuOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" x-trap.noscroll="$store.ui.menuOpen" role="dialog" aria-modal="true" aria-label="Menu">
        <div class="app-header safe-top flex items-center justify-between px-4 py-4">
            <p class="font-display text-lg font-extrabold">Menu</p>
            <button type="button" @click="$store.ui.menuOpen = false" class="icon-btn text-white hover:bg-white/15" aria-label="Close"><x-ico name="close" :size="22" /></button>
        </div>
        <div class="flex-1 overflow-y-auto p-3">
            <p class="px-2 pb-1 pt-2 text-[0.65rem] font-bold uppercase tracking-wider text-mist">Services</p>
            @foreach($servicesNav as $s)<a href="{{ $s->url }}" class="mega-link"><span class="svc-ico h-9 w-9 rounded-xl {{ $s->is_emergency ? 'svc-ico-danger' : '' }}"><x-ico :name="$s->icon ?: 'wrench'" :size="18" /></span>{{ $s->name }}</a>@endforeach
            <p class="px-2 pb-1 pt-4 text-[0.65rem] font-bold uppercase tracking-wider text-mist">More</p>
            @foreach($menus['header'] ?? [] as $item)<a href="{{ $item->url }}" class="mega-link">{{ $item->label }}</a>@endforeach
        </div>
        <div class="safe-bottom grid grid-cols-2 gap-2 border-t border-line p-3">
            <a href="{{ $biz['phone_href'] }}" class="btn btn-outline btn-sm"><x-ico name="phone" :size="16" /> Call</a>
            <a href="{{ route('booking.create') }}" class="btn btn-primary btn-sm">Book</a>
        </div>
    </aside>
</div>