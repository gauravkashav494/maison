@extends('layouts.app')

@section('content')
    @include('home.sections.hero')

    @foreach($sections as $key)
        @switch($key)
            @case('categories') @include('home.sections.categories') @break
            @case('promos') @include('home.sections.promos') @break
            @case('deals') @include('home.sections.deals') @break
            @case('fresh') @include('home.sections.fresh') @break
            @case('banner') @include('home.sections.banner') @break
            @case('bestsellers') @include('home.sections.bestsellers') @break
            @case('new') @include('home.sections.new') @break
            @case('brands') @include('home.sections.brands') @break
            @case('promises') @include('home.sections.promises') @break
            @case('promo') @include('home.sections.promo') @break
        @endswitch
    @endforeach

    <div x-data="productRail('recent')" x-show="items.length" x-cloak class="g-container section">
        <x-section-head title="Recently viewed" />
        <div x-data="rail()" class="relative">
            <div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0">
                <template x-for="p in items" :key="p.id">
                    <div class="w-40 shrink-0 sm:w-44" data-slide>@include('partials.card-dynamic')</div>
                </template>
            </div>
        </div>
    </div>
@endsection
