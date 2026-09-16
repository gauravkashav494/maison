@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
    <x-page-hero eyebrow="The Journal" title="Notes on style, craft and the season." :breadcrumbs="['Journal' => null]" />

    <section class="container-luxe py-12 lg:py-20">
        <div class="grid gap-x-6 gap-y-14 md:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                <a href="{{ $post->url }}" class="group block">
                    <div class="relative aspect-[4/5] overflow-hidden bg-sand"><img src="{{ $post->image_url }}" alt="{{ $post->title }}" loading="lazy" class="img-cover img-zoom"></div>
                    <p class="mt-5 text-[0.625rem] uppercase tracking-[0.22em] text-taupe">{{ $post->category }} · {{ $post->published_at?->format('F Y') }}</p>
                    <h2 class="mt-2 font-serif text-3xl leading-tight"><span class="link-underline">{{ $post->title }}</span></h2>
                    <p class="mt-3 text-sm leading-relaxed text-smoke">{{ $post->excerpt }}</p>
                </a>
            @endforeach
        </div>
        <div class="mt-14">{{ $posts->links() }}</div>
    </section>
@endsection
