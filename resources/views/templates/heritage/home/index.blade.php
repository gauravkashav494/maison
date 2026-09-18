@extends('layouts.app')

@section('content')
    @include('home.sections.hero')

    @foreach($sections as $key)
        @switch($key)
            @case('strip') @include('home.sections.strip') @break
            @case('featured') @include('home.sections.carousel', ['title' => $g['featured_heading'] ?? 'Featured', 'items' => $featuredProducts, 'more' => !empty($g['featured_collection_slug']) ? route('collections.show', $g['featured_collection_slug']) : route('shop.category', 'new-arrivals')]) @break
            @case('combos') @include('home.sections.carousel', ['title' => $g['combos_heading'] ?? 'Combos', 'items' => $combos, 'more' => !empty($g['combos_collection_slug']) ? route('collections.show', $g['combos_collection_slug']) : route('collections.index')]) @break
            @case('bestsellers') @include('home.sections.carousel', ['title' => $g['bestsellers_heading'] ?? 'Our Best Sellers', 'items' => $bestSellers, 'more' => route('shop.category', 'best-sellers')]) @break
            @case('video') @include('home.sections.video') @break
            @case('needs') @include('home.sections.tabs', ['title' => $g['needs_heading'] ?? 'Shop By Need', 'tabs' => $needs, 'id' => 'needs']) @break
            @case('new') @include('home.sections.carousel', ['title' => $g['new_heading'] ?? 'New Arrivals', 'items' => $newArrivals, 'more' => route('shop.category', 'new-arrivals')]) @break
            @case('certifications') @include('home.sections.certifications') @break
            @case('trust') @include('home.sections.trust') @break
            @case('categories') @include('home.sections.tabs', ['title' => $g['categories_heading'] ?? 'Our Range Of Categories', 'tabs' => $categoryTabs, 'id' => 'categories']) @break
            @case('offer') @include('home.sections.offer') @break
            @case('values') @include('home.sections.values') @break
            @case('journal') @include('home.sections.journal') @break
            @case('testimonials') @include('home.sections.testimonials') @break
        @endswitch
    @endforeach
@endsection
