@props(['title', 'eyebrow' => 'My account'])
@php
    $nav = [
        ['Overview', route('account.index'), 'account'],
        ['Orders', route('account.orders'), 'account/orders*'],
        ['Wishlist', route('account.wishlist'), 'account/wishlist'],
        ['Addresses', route('account.addresses'), 'account/addresses'],
        ['Account information', route('account.profile'), 'account/profile'],
    ];
@endphp
<section class="container-luxe pt-20 pb-20 lg:pt-40 lg:pb-28">
    <div class="border-b border-ink/10 pb-8">
        <p class="eyebrow text-taupe">{{ $eyebrow }}</p>
        <h1 class="display-md mt-3">{{ $title }}</h1>
    </div>
    <div class="grid gap-12 pt-10 lg:grid-cols-12 lg:gap-16">
        <aside class="min-w-0 lg:col-span-3">
            <nav class="no-scrollbar -mx-5 flex gap-6 overflow-x-auto px-5 text-[0.6875rem] uppercase tracking-[0.2em] lg:mx-0 lg:flex-col lg:gap-4 lg:px-0" aria-label="Account">
                @foreach($nav as [$label, $url, $pattern])
                    <a href="{{ $url }}" class="link-underline shrink-0 pb-0.5 {{ request()->is($pattern) ? 'text-ink' : 'text-smoke hover:text-ink' }}" data-active="{{ request()->is($pattern) ? 'true' : 'false' }}">{{ $label }}</a>
                @endforeach
                @auth
                    <form method="post" action="{{ route('logout') }}" class="shrink-0">@csrf<button type="submit" class="link-underline pb-0.5 text-smoke hover:text-ink">Sign out</button></form>
                @endauth
            </nav>
        </aside>
        <div class="min-w-0 lg:col-span-9">
            @if(session('status'))<p class="mb-6 border border-emerald-700/20 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>@endif
            {{ $slot }}
        </div>
    </div>
</section>
