@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
    <x-page-hero eyebrow="Search" :title="$q ? 'Results for “' . $q . '”' : 'Search'" :description="$q ? $products->total() . ' pieces found.' : 'Search products, categories and collections.'" :breadcrumbs="['Search' => null]">
        <form method="get" action="{{ route('search') }}" class="mt-8 flex max-w-lg items-center gap-4 border-b border-ink pb-3">
            <x-ico name="search" :size="20" />
            <input type="search" name="q" value="{{ $q }}" placeholder="Search…" class="w-full bg-transparent font-serif text-2xl outline-none placeholder:text-taupe" aria-label="Search">
        </form>
    </x-page-hero>

    <section class="container-luxe py-12 lg:py-16">
        @if($q && $products->isEmpty())
            <p class="py-20 text-center font-serif text-2xl text-smoke">Nothing matched “{{ $q }}”. Try a different term.</p>
        @elseif($q)
            <div class="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-6 lg:gap-y-14">
                @foreach($products as $p)<x-product-card :product="$p" :show-rating="true" />@endforeach
            </div>
            <div class="mt-14">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
