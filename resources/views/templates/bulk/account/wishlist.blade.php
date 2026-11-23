@extends('layouts.app')

{{--
    Saved items (the wishlist). The list is kept on this device ($store.wishlist), so the page
    sends the saved ids back to this route (?ids=…) and shows what comes back
    (account/wishlist-items): the same order list as the shop, with a quantity box on every
    row and "Add all to order". Opens for guests too.
--}}

@section('content')
<x-account-shell title="Saved items" text="Products you saved for later. Saved items stay on this device.">
    <div x-data="savedList(@js(route('account.wishlist')))">
        <div x-show="loading" class="bk-card bk-muted p-8 text-center text-sm" role="status">Loading saved items…</div>

        <div x-show="!loading && !html" x-cloak class="bk-card p-8 text-center">
            <x-heroicon-o-heart class="mx-auto size-10 text-brand" aria-hidden="true" />
            <p class="mt-3 text-lg font-extrabold">Nothing saved yet</p>
            <p class="bk-muted mx-auto mt-1 max-w-md text-sm">Tap the heart on any product to keep it here, then order everything you need from this list in one go.</p>
            <div class="mt-5 flex flex-wrap justify-center gap-3">
                <a href="{{ route('shop.index') }}" class="bk-btn bk-btn-primary">Browse products</a>
                <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-outline">Bulk order</a>
            </div>
        </div>

        <div x-show="!loading && html" x-cloak x-html="html"></div>
    </div>
</x-account-shell>
@endsection
