{{--
    Header account control, shared by every template. A guest gets the plain link to sign in;
    a signed-in customer gets a small menu with their account, orders and Log out, so signing
    out is one tap away from any page (not only from inside the account area).

    Each template passes its own look: `class` styles the trigger (its slot is the icon/label),
    `wrapper` the positioned box around it (e.g. `hidden sm:block`), `panel` the dropdown and
    `item` each row. The structural classes below are listed in each template's @source.
--}}
@props([
    'wrapper' => '',
    'panel' => 'border border-black/10 bg-white text-sm shadow-lg',
    'item' => 'hover:bg-black/5',
    'muted' => 'opacity-60',
    'orders' => 'account.orders',
    'ordersLabel' => 'My orders',
])
@guest
    <div class="{{ $wrapper }}"><a href="{{ route('login') }}" {{ $attributes }}>{{ $slot }}</a></div>
@else
    <div class="relative {{ $wrapper }}" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
        <button type="button" {{ $attributes }} @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu">{{ $slot }}</button>
        <div x-cloak x-show="open" x-transition.opacity.duration.150ms role="menu"
             class="absolute right-0 top-full z-[60] mt-2 w-56 overflow-hidden py-1 text-left {{ $panel }}">
            <p class="truncate px-4 pb-2 pt-2 text-xs {{ $muted }}">Signed in as <span class="font-semibold">{{ auth()->user()->name }}</span></p>
            <a href="{{ route('account.index') }}" role="menuitem" class="block px-4 py-2 {{ $item }}">My account</a>
            @if(\Illuminate\Support\Facades\Route::has($orders))
                <a href="{{ route($orders) }}" role="menuitem" class="block px-4 py-2 {{ $item }}">{{ $ordersLabel }}</a>
            @endif
            <form method="post" action="{{ route('logout') }}" novalidate>
                @csrf
                <button type="submit" role="menuitem" class="block w-full px-4 py-2 text-left {{ $item }}">Log out</button>
            </form>
        </div>
    </div>
@endguest
