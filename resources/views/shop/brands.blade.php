@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

{{-- /brands: every brand with something on sale. Each links to its page (/brands/{slug}). --}}

@section('content')
    <x-page-hero eyebrow="The Houses" title="Brands" description="Every house we carry, in one place." :breadcrumbs="['Shop' => route('shop.index'), 'Brands' => null]" />

    <section class="container-luxe py-12 lg:py-20">
        @if($brands->isEmpty())
            <p class="text-center text-sm text-smoke">No brands yet. <a href="{{ route('shop.index') }}" class="link-underline">Shop all pieces</a></p>
        @else
            <div class="grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($brands as $b)
                    <a href="{{ $b->url }}" class="group block">
                        <div class="grid aspect-[4/3] place-items-center bg-sand p-8">
                            @if($b->logo_url)
                                <img src="{{ $b->logo_url }}" alt="{{ $b->name }}" loading="lazy" class="max-h-full max-w-full object-contain transition-transform duration-500 group-hover:scale-105">
                            @else
                                <span class="font-serif text-5xl text-taupe">{{ Str::upper(Str::substr($b->name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div class="mt-4 flex items-baseline justify-between gap-4">
                            <h2 class="font-serif text-2xl leading-tight"><span class="link-underline">{{ $b->name }}</span></h2>
                            <span class="shrink-0 text-[0.625rem] uppercase tracking-[0.2em] text-taupe">{{ $b->products_count }} {{ Str::plural('piece', $b->products_count) }}</span>
                        </div>
                        @if($b->description)<p class="mt-2 line-clamp-2 text-sm leading-relaxed text-smoke">{{ $b->description }}</p>@endif
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
