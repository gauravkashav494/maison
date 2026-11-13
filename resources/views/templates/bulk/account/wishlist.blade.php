@extends('layouts.app')

{{--
    This store has no wishlist (buyers repeat orders instead), but the shared route still exists.
    Rather than the shared design, it points the buyer to what this store does have.
--}}

@section('content')
<x-account-shell title="Saved items">
    <div class="bk-card p-8 text-center">
        <x-heroicon-o-arrow-path class="mx-auto size-10 text-brand" aria-hidden="true" />
        <p class="mt-3 text-lg font-extrabold">Repeat an order instead</p>
        <p class="bk-muted mx-auto mt-1 max-w-md text-sm">This store doesn't keep a wishlist. Load any past order into Bulk order, change what you need and add it in one go.</p>
        <div class="mt-5 flex flex-wrap justify-center gap-3">
            <a href="{{ url('/#bulk-order') }}" class="bk-btn bk-btn-primary">Bulk order</a>
            <a href="{{ route('account.orders') }}" class="bk-btn bk-btn-outline">My orders</a>
        </div>
    </div>
</x-account-shell>
@endsection
