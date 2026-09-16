@extends('layouts.app', ['transparentHeader' => true])

@section('content')
    @include('home.sections.hero')
    @include('home.sections.ticker')
    @include('home.sections.categories')
    @include('home.sections.new-arrivals')
    @include('home.sections.editorial')
    @if($featured) @include('home.sections.featured-collection') @endif
    @include('home.sections.best-sellers')
    @if($fragrance) @include('home.sections.fragrance') @endif
    @if($posts->isNotEmpty()) @include('home.sections.journal') @endif
    @include('home.sections.promises')
    @include('home.sections.newsletter')
@endsection
