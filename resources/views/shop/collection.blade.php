@extends('layouts.app', ['transparentHeader' => true])

@section('content')
{{-- Campaign hero --}}
<section class="relative min-h-[80svh] overflow-hidden bg-ink text-ivory" x-data="parallax(0.06)">
    <div class="absolute inset-x-0 -top-[8%] h-[116%] will-change-transform" :style="`transform: translateY(${y}%)`">
        <img src="{{ $collection->hero_image_url }}" alt="{{ $collection->name }}" fetchpriority="high" class="img-cover absolute inset-0 object-[50%_25%]">
    </div>
    <div class="absolute inset-0 bg-gradient-to-t from-ink/80 via-ink/30 to-ink/30"></div>
    <div class="container-luxe relative flex min-h-[80svh] flex-col justify-end pb-16 pt-40 lg:pb-20">
        <nav aria-label="Breadcrumb" class="mb-6 flex items-center gap-2 text-[0.625rem] uppercase tracking-[0.2em] text-ivory/60 animate-hero-in"><a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('collections.index') }}">Collections</a><span>/</span><span class="text-ivory">{{ $collection->name }}</span></nav>
        @if($collection->season)<p class="eyebrow text-gold-light animate-hero-in" style="animation-delay:.2s">{{ $collection->season }}</p>@endif
        <h1 class="display-xl mt-4 max-w-4xl text-balance animate-hero-in" style="animation-delay:.35s">{{ $collection->name }}</h1>
        <p class="mt-6 max-w-xl text-[0.9375rem] leading-relaxed text-ivory/75 animate-hero-in" style="animation-delay:.5s">{{ $collection->description }}</p>
        <div class="mt-8 flex flex-wrap gap-4 animate-hero-in" style="animation-delay:.65s"><a href="#pieces" class="btn btn-light btn-lg">Shop the collection <x-ico name="arrow-right" :size="14" class="btn-arrow" /></a><span class="self-center text-[0.625rem] uppercase tracking-[0.22em] text-ivory/60">{{ $products->count() }} pieces</span></div>
    </div>
</section>

{{-- Featured pieces — editorial triptych --}}
@if($featured->count() >= 3)
    <section class="container-luxe py-20 lg:py-28">
        <x-section-header eyebrow="The edit" title="Pieces that define the collection." align="left" />
        <div class="mt-12 grid gap-6 lg:grid-cols-12">
            @foreach($featured as $i => $p)
                <a href="{{ $p->url }}" class="group block {{ $i === 0 ? 'lg:col-span-6 lg:row-span-2' : 'lg:col-span-3' }} {{ $i === 2 ? 'lg:col-start-10 lg:mt-24' : '' }} {{ $i === 1 ? 'lg:col-start-7' : '' }}">
                    <div class="img-reveal relative {{ $i === 0 ? 'aspect-[4/5]' : 'aspect-[3/4]' }} bg-sand" style="--reveal-delay: {{ $i * .12 }}s" x-data x-intersect.once.threshold.2="$el.classList.add('is-visible')">
                        <div class="img-reveal-clip"><img src="{{ $p->image_url }}" alt="{{ $p->name }}" loading="lazy" class="img-cover img-zoom absolute inset-0"></div>
                    </div>
                    <div class="mt-4 flex items-baseline justify-between gap-4"><div><p class="text-[0.5625rem] uppercase tracking-[0.2em] text-taupe">{{ $p->category?->name }}</p><h3 class="mt-1 font-serif {{ $i === 0 ? 'text-3xl' : 'text-xl' }}"><span class="link-underline">{{ $p->name }}</span></h3></div><span class="tabular-nums text-sm">{{ money($p->price) }}</span></div>
                </a>
            @endforeach
        </div>
    </section>
@endif

{{-- Story --}}
@if($collection->body)
    <section class="border-y border-ink/10 bg-cream">
        <div class="container-luxe py-16 lg:py-24">
            <div class="mx-auto max-w-3xl">
                <p class="eyebrow text-center text-taupe">The story</p>
                <div class="prose-luxe mt-8 text-[1.0625rem]">{!! $collection->body !!}</div>
            </div>
        </div>
    </section>
@endif

{{-- All pieces --}}
<section id="pieces" class="container-luxe scroll-mt-24 py-16 lg:py-24">
    <x-section-header eyebrow="Shop" :title="'All ' . $collection->name . ' pieces'" :description="$products->count() . ' pieces in this edit.'" cta="Shop everything" :cta-url="route('shop.index')" />
    <div class="mt-12 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-6 lg:gap-y-14">
        @foreach($products as $p)<x-product-card :product="$p" :show-rating="true" />@endforeach
    </div>
</section>

{{-- Related --}}
@if($related->isNotEmpty())
    <section class="border-t border-ink/10 bg-cream">
        <div class="container-luxe py-16 lg:py-24">
            <x-section-header eyebrow="Continue" title="Related collections" cta="All collections" :cta-url="route('collections.index')" />
            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach($related as $c)
                    <a href="{{ $c->url }}" class="group block">
                        <div class="relative aspect-[4/3] overflow-hidden bg-sand"><img src="{{ $c->image_url }}" alt="{{ $c->name }}" loading="lazy" class="img-cover img-zoom"><div class="absolute inset-0 bg-gradient-to-t from-ink/50 to-transparent"></div><span class="absolute bottom-4 left-4 font-serif text-2xl text-ivory">{{ $c->name }}</span></div>
                        <p class="mt-3 text-sm text-smoke">{{ $c->description }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection
