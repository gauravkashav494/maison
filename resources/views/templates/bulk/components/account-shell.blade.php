{{--
    The signed-in buyer's pages: a side menu (a scrolling row on phones) beside the page.
    Replaces the shared account shell, so every account page reads as this store. There is no
    wishlist here: the store has no "save for later" — buyers repeat orders instead.
--}}
@props(['title', 'text' => null])
@php
    $nav = [
        ['Overview', route('account.index'), 'account', 'squares-2x2'],
        ['Orders', route('account.orders'), 'account/orders*', 'clipboard-document-list'],
        ['Addresses', route('account.addresses'), 'account/addresses', 'map-pin'],
        ['Account details', route('account.profile'), 'account/profile', 'user'],
    ];
@endphp

<div class="bk-wrap mt-6">
    <div class="grid items-start gap-5 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <nav class="bk-card bk-account-nav" aria-label="My account">
            @foreach($nav as [$label, $url, $pattern, $icon])
                <a href="{{ $url }}" @class(['is-on' => request()->is($pattern)]) @if(request()->is($pattern)) aria-current="page" @endif>
                    <x-dynamic-component :component="'heroicon-o-'.$icon" aria-hidden="true" />{{ $label }}
                </a>
            @endforeach
            <form method="post" action="{{ route('logout') }}" novalidate>
                @csrf
                <button type="submit"><x-heroicon-o-arrow-right-start-on-rectangle aria-hidden="true" />Sign out</button>
            </form>
        </nav>

        <div class="min-w-0">
            <x-page-head :title="$title" :text="$text">{{ $actions ?? '' }}</x-page-head>
            @if(session('status'))
                <p class="bk-notice mt-4" role="status"><x-heroicon-o-check-circle aria-hidden="true" />{{ session('status') }}</p>
            @endif
            <div class="mt-5">{{ $slot }}</div>
        </div>
    </div>
</div>
