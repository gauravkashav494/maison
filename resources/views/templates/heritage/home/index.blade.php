@extends('layouts.app')

@section('content')
    @include('home.sections.hero')

    @foreach($sections as $key)
        @switch($key)
            @case('categories') @include('home.sections.categories') @break
            @case('featured') @include('home.sections.featured') @break
            @case('offer') @include('home.sections.offer') @break
            @case('bestsellers') @include('home.sections.bestsellers') @break
            @case('story') @include('home.sections.story') @break
            @case('needs') @include('home.sections.needs') @break
            @case('brands') @include('home.sections.brands') @break
            @case('testimonials') @include('home.sections.testimonials') @break
            @case('journal') @include('home.sections.journal') @break
            @case('trust') @include('home.sections.trust') @break
        @endswitch
    @endforeach

    <div x-data="productRail('recent')" x-show="items.length" x-cloak class="h-container section !pt-0">
        <x-section-head eyebrow="Recently viewed" title="Pick up where you left off" />
        <div x-data="rail()" class="relative"><div x-ref="track" class="rail no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0"><template x-for="p in items" :key="p.id"><div class="w-[11.5rem] shrink-0 sm:w-56" data-slide>@include('partials.card-dynamic')</div></template></div></div>
    </div>
@endsection
