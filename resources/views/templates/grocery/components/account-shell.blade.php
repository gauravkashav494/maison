@props(['title', 'eyebrow' => 'My account'])
@php
    $nav = [
        ['Overview', route('account.index'), 'account', 'home'],
        ['My orders', route('account.orders'), 'account/orders*', 'package'],
        ['Saved items', route('account.wishlist'), 'account/wishlist', 'heart'],
        ['Addresses', route('account.addresses'), 'account/addresses', 'pin'],
        ['Profile & password', route('account.profile'), 'account/profile', 'user'],
    ];
@endphp
<x-page-head :title="$title" :breadcrumbs="[$eyebrow => route('account.index'), $title => null]" />
<section class="g-container grid gap-6 py-6 lg:grid-cols-12 lg:gap-8">
    <aside class="lg:col-span-3">
        <nav class="card no-scrollbar flex gap-1 overflow-x-auto p-2 lg:flex-col" aria-label="Account">
            @foreach($nav as [$label, $url, $pattern, $icon])
                <a href="{{ $url }}" class="flex shrink-0 items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-semibold {{ request()->is($pattern) ? 'bg-leaf-light text-leaf-dark' : 'text-slate hover:bg-paper hover:text-ink' }}"><x-ico :name="$icon" :size="18" /> {{ $label }}</a>
            @endforeach
            @auth
                <form method="post" action="{{ route('logout') }}" class="shrink-0 lg:mt-2 lg:border-t lg:border-line lg:pt-2">@csrf<button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate hover:bg-paper hover:text-berry"><x-ico name="logout" :size="18" /> Log out</button></form>
            @endauth
        </nav>
    </aside>
    <div class="lg:col-span-9">
        @if(session('status'))<p class="mb-4 flex items-center gap-2 rounded-xl bg-leaf-light px-4 py-3 text-sm font-semibold text-leaf-dark"><x-ico name="check" :size="16" /> {{ session('status') }}</p>@endif
        {{ $slot }}
    </div>
</section>
