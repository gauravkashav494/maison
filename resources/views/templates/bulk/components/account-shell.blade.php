{{--
    The signed-in buyer's pages: a side menu (a scrolling row on phones) beside the page.
    Replaces the shared account shell, so every account page reads as this store. Saved items
    (the wishlist) opens for guests too, so Sign out shows only when someone is signed in.
--}}
@props(['title', 'text' => null])
@php
    $nav = [
        ['Overview', route('account.index'), 'account', 'squares-2x2'],
        ['Orders', route('account.orders'), 'account/orders*', 'clipboard-document-list'],
        ['Saved items', route('account.wishlist'), 'account/wishlist', 'heart'],
        ['Addresses', route('account.addresses'), 'account/addresses', 'map-pin'],
        ['Account details', route('account.profile'), 'account/profile', 'user'],
    ];
@endphp

<div class="bk-wrap mt-6">
    <div class="bk-account grid items-start gap-5 lg:grid-cols-[13rem_minmax(0,1fr)]">
        <nav class="bk-card bk-account-nav" aria-label="My account">
            @foreach($nav as [$label, $url, $pattern, $icon])
                <a href="{{ $url }}" @class(['is-on' => request()->is($pattern)]) @if(request()->is($pattern)) aria-current="page" @endif>
                    <x-dynamic-component :component="'heroicon-o-'.$icon" aria-hidden="true" />{{ $label }}
                </a>
            @endforeach
            @auth
                <form method="post" action="{{ route('logout') }}" novalidate>
                    @csrf
                    <button type="submit"><x-heroicon-o-arrow-right-start-on-rectangle aria-hidden="true" />Sign out</button>
                </form>
            @endauth
        </nav>

        <div class="min-w-0">
            <x-page-head :title="$title" :text="$text">{{ $actions ?? '' }}</x-page-head>
            @if(session('status'))
                <p class="bk-notice mt-3" role="status"><x-heroicon-o-check-circle aria-hidden="true" />{{ session('status') }}</p>
            @endif
            <div class="mt-4">{{ $slot }}</div>
        </div>
    </div>
</div>
