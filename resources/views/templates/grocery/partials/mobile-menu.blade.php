{{-- Account / quick-links drawer (opened from the account page header on mobile) --}}
@php $quickLinks = $menus['header'] ?? collect(); $help = $menus['footer_help'] ?? collect(); @endphp
<div x-data x-show="$store.ui.menuOpen" x-cloak>
    <div class="overlay" x-show="$store.ui.menuOpen" x-transition.opacity @click="$store.ui.closeAll()"></div>
    <div class="sheet sheet-right" x-show="$store.ui.menuOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" x-trap.noscroll="$store.ui.menuOpen">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <p class="text-lg font-extrabold">Menu</p>
            <button type="button" @click="$store.ui.closeAll()" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-paper" aria-label="Close"><x-ico name="close" :size="20" /></button>
        </div>
        <div class="flex-1 overflow-y-auto p-3">
            <ul class="space-y-1">
                @foreach($quickLinks as $item)<li><a href="{{ $item->href }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-paper {{ $item->is_accent ? 'text-berry' : '' }}">{{ $item->label }}</a></li>@endforeach
            </ul>
            @if($help->isNotEmpty())
                <p class="mt-5 px-3 text-xs font-bold uppercase tracking-wider text-mist">Help</p>
                <ul class="mt-1 space-y-1">@foreach($help as $item)<li><a href="{{ $item->href }}" class="block rounded-lg px-3 py-2 text-sm text-slate hover:bg-paper">{{ $item->label }}</a></li>@endforeach</ul>
            @endif
        </div>
        <div class="border-t border-line p-4">
            @auth
                <a href="{{ route('account.index') }}" class="btn btn-outline btn-block">My account</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary btn-block">Login / Sign up</a>
            @endauth
        </div>
    </div>
</div>
