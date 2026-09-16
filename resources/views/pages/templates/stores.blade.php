@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
<x-page-hero :eyebrow="$page->eyebrow" :title="$page->title" :description="$page->excerpt" :breadcrumbs="[$page->title => null]" />

<section class="container-luxe py-12 lg:py-20">
    @if($page->body)<div class="prose-luxe mb-14 max-w-2xl">{!! $page->body !!}</div>@endif
    <div class="grid gap-x-6 gap-y-14 md:grid-cols-2 lg:grid-cols-3">
        @foreach($stores as $i => $s)
            <article class="group reveal" style="--reveal-delay: {{ ($i % 3) * .08 }}s" x-data x-intersect.once.threshold.10="$el.classList.add('is-visible')">
                <div class="relative aspect-[4/3] overflow-hidden bg-sand">@if($s->image_url)<img src="{{ $s->image_url }}" alt="{{ $s->name }}" loading="lazy" class="img-cover img-zoom">@endif</div>
                <p class="eyebrow mt-5 text-[0.5625rem] text-taupe">{{ $s->city }}@if($s->country && $s->country !== 'India'), {{ $s->country }}@endif</p>
                <h2 class="mt-1 font-serif text-3xl">{{ $s->name }}</h2>
                <p class="mt-3 text-sm text-smoke">{{ $s->address }}, {{ $s->city }}@if($s->postal_code) {{ $s->postal_code }}@endif</p>
                @if($s->hours)<p class="mt-3 whitespace-pre-line text-sm leading-relaxed">{{ $s->hours }}</p>@endif
                <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-[0.625rem] uppercase tracking-[0.2em]">
                    @if($s->phone)<a href="tel:{{ preg_replace('/\s+/', '', $s->phone) }}" class="link-underline">{{ $s->phone }}</a>@endif
                    @if($s->email)<a href="mailto:{{ $s->email }}" class="link-underline">Email</a>@endif
                    @if($s->maps_url)<a href="{{ $s->maps_url }}" target="_blank" rel="noreferrer" class="link-underline">Directions →</a>@endif
                </div>
            </article>
        @endforeach
    </div>
</section>

@push('head')
<script type="application/ld+json">{!! json_encode($stores->map(fn ($s) => array_filter(['@context' => 'https://schema.org', '@type' => 'Store', 'name' => $s->name, 'telephone' => $s->phone, 'email' => $s->email, 'address' => ['@type' => 'PostalAddress', 'streetAddress' => $s->address, 'addressLocality' => $s->city, 'addressRegion' => $s->state, 'postalCode' => $s->postal_code, 'addressCountry' => $s->country], 'geo' => $s->lat ? ['@type' => 'GeoCoordinates', 'latitude' => $s->lat, 'longitude' => $s->lng] : null]))->values(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@endsection
