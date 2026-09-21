@props(['title', 'eyebrow' => 'My account'])
@php
    $nav = [
        ['Overview', route('account.index'), 'account', 'home'],
        ['My bookings', route('account.bookings'), 'account/bookings*', 'calendar'],
        ['Addresses', route('account.addresses'), 'account/addresses', 'map-pin'],
        ['Profile & password', route('account.profile'), 'account/profile', 'user'],
    ];
@endphp
<x-page-head :title="$title" :breadcrumbs="[$eyebrow => route('account.index'), $title => null]" />
<section class="ps-container grid gap-6 py-5 lg:grid-cols-12 lg:gap-8 lg:py-8">
    <aside class="min-w-0 lg:col-span-3">
        <nav class="card no-scrollbar flex gap-1 overflow-x-auto p-2 lg:flex-col" aria-label="Account">
            @foreach($nav as [$label, $url, $pattern, $icon])
                <a href="{{ $url }}" class="flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->is($pattern) ? 'bg-sky text-primary' : 'text-slate hover:bg-canvas hover:text-ink' }}"><x-ico :name="$icon" :size="18" /> {{ $label }}</a>
            @endforeach
            @auth
                <form method="post" action="{{ route('logout') }}" class="shrink-0 lg:mt-2 lg:border-t lg:border-line lg:pt-2">@csrf<button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate hover:bg-canvas hover:text-danger"><x-ico name="logout" :size="18" /> Log out</button></form>
            @endauth
        </nav>
    </aside>
    <div class="min-w-0 lg:col-span-9">
        @if(session('status'))<p class="mb-4 flex items-center gap-2 rounded-xl bg-success-light px-4 py-3 text-sm font-semibold text-success"><x-ico name="check" :size="16" /> {{ session('status') }}</p>@endif
        {{ $slot }}
    </div>
</section>