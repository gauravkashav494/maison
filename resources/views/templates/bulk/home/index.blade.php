@extends('layouts.app')

@section('content')
    @include('home.sections.hero')
    @include('home.sections.order')

    @foreach($sections as $key)
        @switch($key)
            @case('categories') @include('home.sections.categories') @break
            @case('featured') @include('home.sections.featured') @break
            @case('deals') @include('home.sections.deals') @break
            @case('bestsellers') @include('home.sections.bestsellers') @break
            @case('bulk') @include('home.sections.bulk') @break
            @case('brands') @include('home.sections.brands') @break
            @case('new') @include('home.sections.new') @break
            @case('why') @include('home.sections.why') @break
            @case('faq') @include('home.sections.faq') @break
        @endswitch
    @endforeach
@endsection
