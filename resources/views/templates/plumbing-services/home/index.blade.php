@extends('layouts.app')

@php $seo->jsonLd[] = \App\Http\Controllers\Storefront\ServicesController::localBusiness(); @endphp

@section('content')
    @include('home.sections.hero')
    @include('home.sections.quick')
    @foreach($sections as $key)
        @includeIf("home.sections.{$key}")
    @endforeach
@endsection