@extends('layouts.app')

@section('content')
<x-account-shell title="Wishlist" eyebrow="Saved pieces">
    <div x-data="productRail('wishlist')">
        <p class="text-sm text-smoke" x-show="loading">Loading your wishlist…</p>
        <div x-show="!loading && !items.length" x-cloak class="border border-dashed border-ink/15 p-10 text-center">
            <p class="font-serif text-2xl">Nothing saved yet</p>
            <p class="mt-2 text-sm text-smoke">Tap the heart on any piece to keep it here.</p>
            <a href="{{ route('shop.index') }}" class="btn btn-outline mt-6">Browse the shop</a>
        </div>
        <div x-show="items.length" x-cloak class="grid grid-cols-2 gap-x-4 gap-y-10 lg:grid-cols-3 lg:gap-x-6">
            <template x-for="p in items" :key="p.id">
                <article class="group relative">
                    <a :href="p.url" class="relative block aspect-[3/4] overflow-hidden bg-sand"><img :src="p.images[0]" :alt="p.name" class="img-cover img-zoom"></a>
                    <button type="button" @click="$store.wishlist.toggle(p.id)" class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-ivory/85 backdrop-blur" aria-label="Remove from wishlist"><x-ico name="close" :size="14" /></button>
                    <p class="mt-4 text-[0.5625rem] uppercase tracking-[0.2em] text-taupe" x-text="p.category"></p>
                    <a :href="p.url" class="mt-1 block font-serif text-[1.0625rem] leading-snug" x-text="p.name"></a>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-sm tabular-nums" x-text="p.price_formatted"></span>
                        <button type="button" @click="$store.ui.showQuickView(p.slug)" class="text-[0.625rem] uppercase tracking-[0.2em] underline-offset-4 hover:underline">Add to bag</button>
                    </div>
                </article>
            </template>
        </div>
        @guest<p class="mt-10 text-xs text-smoke">Your wishlist is saved on this device. <a href="{{ route('login') }}" class="underline underline-offset-4">Sign in</a> to keep it with your account.</p>@endguest
    </div>
</x-account-shell>
@endsection
