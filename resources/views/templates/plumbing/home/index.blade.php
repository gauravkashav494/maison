@extends('layouts.app')

@section('content')
    @include('home.sections.hero')

    @foreach($sections as $key)
        @switch($key)
            @case('categories') @include('home.sections.categories') @break
            @case('deals') @include('home.sections.deals') @break
            @case('projects') @include('home.sections.projects') @break
            @case('featured') @include('home.sections.featured') @break
            @case('promos') @include('home.sections.promos') @break
            @case('bestsellers') @include('home.sections.bestsellers') @break
            @case('bulk') @include('home.sections.bulk') @break
            @case('brands') @include('home.sections.brands') @break
            @case('new') @include('home.sections.new') @break
            @case('why') @include('home.sections.why') @break
            @case('faq') @include('home.sections.faq') @break
        @endswitch
    @endforeach

    <div x-data="productRail('recent')" x-show="items.length" x-cloak class="p-container section">
        <x-section-head title="Recently viewed" />
        <div x-data="rail()" class="relative">
            <div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0">
                <template x-for="p in items" :key="p.id">
                    <div class="w-[11.5rem] shrink-0 sm:w-[13.5rem] lg:w-[15rem]" data-slide>@include('partials.card-dynamic')</div>
                </template>
            </div>
        </div>
    </div>
@endsection
