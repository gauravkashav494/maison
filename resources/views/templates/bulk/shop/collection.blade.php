@extends('layouts.app')

{{--
    One collection: its description, then its products as the same order list as the catalogue
    (a row per size, quantities, "Add all to order"). Replaces the shared campaign page.
--}}

@section('content')
<div class="bk-wrap mt-6">
    <x-page-head :title="$collection->name" :text="$collection->description" :crumbs="['Collections' => route('collections.index'), $collection->name => null]" />

    @if($collection->body)
        <div class="bk-card bk-prose mt-5 p-5 sm:p-6">{!! $collection->body !!}</div>
    @endif

    <div class="mt-5">
        @if($products->isEmpty())
            <div class="bk-card p-10 text-center"><p class="font-extrabold">Nothing in this collection right now</p></div>
        @else
            <x-product-list :products="$products" :show-stock="(bool) tsetting('site.show_stock', true)" />
        @endif
    </div>

    @if($related->isNotEmpty())
        <section class="mt-10">
            <div class="bk-section-head"><div><h2>More collections</h2></div><a href="{{ route('collections.index') }}" class="bk-link text-sm">All collections →</a></div>
            <div class="flex flex-wrap gap-2">
                @foreach($related as $c)<a href="{{ $c->url }}" class="bk-btn bk-btn-outline">{{ $c->name }}</a>@endforeach
            </div>
        </section>
    @endif
</div>
@endsection
