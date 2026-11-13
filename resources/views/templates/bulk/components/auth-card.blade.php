{{--
    The store's sign-in layout, shared by sign in, create account, forgot and reset password:
    a brand panel with what a trade account gives a buyer here (all of it real in this store)
    beside the form. The form itself is the slot.
--}}
@props(['heading', 'intro' => null])

@php
    $storeName = setting('site.name', config('app.name'));
    // An uploaded file or a full URL; only an http(s) URL reaches the style attribute.
    $image = \App\Support\Media::url(tsetting('site.login_image'));
    $image = $image && preg_match('#^(https?:)?//|^/#', $image) ? $image : null;
@endphp

<div class="bk-wrap my-8 sm:my-12">
    <div class="bk-card mx-auto grid max-w-4xl overflow-hidden md:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)]">
        <aside class="bk-auth-panel hidden p-8 md:flex md:flex-col lg:p-10"
               @if($image) style="--bk-auth-img: url('{{ str_replace(["'", '(', ')'], ['%27', '%28', '%29'], $image) }}')" @endif>
            <p class="bk-logo"><span class="bk-logo-mark"><x-heroicon-o-cube aria-hidden="true" /></span><span>{{ $storeName }}<small>Trade supplies</small></span></p>
            <p class="mt-10 text-2xl font-extrabold leading-tight">Your trade account</p>
            <ul class="mt-6 space-y-4 text-sm">
                <li class="flex gap-3"><x-heroicon-o-arrow-path class="size-5 shrink-0" aria-hidden="true" /><span><strong class="block">Repeat any order</strong><span class="opacity-75">Load a past order into the bulk order and add it again.</span></span></li>
                <li class="flex gap-3"><x-heroicon-o-map-pin class="size-5 shrink-0" aria-hidden="true" /><span><strong class="block">Saved delivery addresses</strong><span class="opacity-75">Checkout fills them in for you.</span></span></li>
                <li class="flex gap-3"><x-heroicon-o-truck class="size-5 shrink-0" aria-hidden="true" /><span><strong class="block">Order history and tracking</strong><span class="opacity-75">Every order, its items and where it is now.</span></span></li>
            </ul>
            @if($phone = tsetting('site.support_phone'))
                <p class="mt-auto pt-10 text-sm opacity-80">Questions? Call the trade desk on <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="font-bold text-white underline">{{ $phone }}</a></p>
            @endif
        </aside>

        <div class="p-6 sm:p-10">
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $heading }}</h1>
            @if($intro)<p class="bk-muted mt-2 text-sm">{{ $intro }}</p>@endif

            @if(session('status'))
                <p class="mt-5 rounded-lg bg-brand-soft p-3 text-sm font-semibold text-brand-ink" role="status">{{ session('status') }}</p>
            @endif

            <div class="mt-6">{{ $slot }}</div>
        </div>
    </div>
</div>
