@extends('layouts.app')

@section('content')
<x-account-shell title="Saved items">
    <div x-data="productRail('wishlist')">
        <template x-if="!loading && !items.length">
            <div>
                <x-empty-state icon="heart" title="Nothing saved yet" text="Tap the heart on any product to keep it here for later. Saved items stay on this device.">
                    <a href="{{ route('shop.index') }}" class="btn btn-primary">Browse products</a>
                </x-empty-state>
            </div>
        </template>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            <template x-for="p in items" :key="p.id">
                <div>@include('partials.card-dynamic')</div>
            </template>
        </div>
    </div>
</x-account-shell>
@endsection
