@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
    <x-page-hero eyebrow="The Edits" title="Collections" description="Editorial edits for every season and occasion — each one a complete point of view." :breadcrumbs="['Collections' => null]" />

    <section class="container-luxe py-12 lg:py-20">
        <div class="grid gap-x-6 gap-y-14 md:grid-cols-2 lg:grid-cols-3">
            @foreach($collections as $i => $c)
                <a href="{{ $c->url }}" class="group reveal block {{ $i % 3 === 1 ? 'lg:mt-16' : '' }}" style="--reveal-delay: {{ ($i % 3) * 0.08 }}s" x-data x-intersect.once.threshold.10="$el.classList.add('is-visible')">
                    <div class="relative aspect-[4/5] overflow-hidden bg-sand">
                        <img src="{{ $c->image_url }}" alt="{{ $c->name }}" loading="lazy" class="img-cover img-zoom">
                    </div>
                    <div class="mt-5 flex items-baseline justify-between gap-4">
                        <div>
                            @if($c->season)<p class="eyebrow text-[0.5625rem] text-taupe">{{ $c->season }}</p>@endif
                            <h2 class="mt-1 font-serif text-3xl leading-tight"><span class="link-underline">{{ $c->name }}</span></h2>
                        </div>
                        <span class="text-[0.625rem] uppercase tracking-[0.2em] text-taupe">{{ $c->products_count }} pieces</span>
                    </div>
                    <p class="mt-2 max-w-sm text-sm leading-relaxed text-smoke">{{ $c->description }}</p>
                    <span class="eyebrow mt-4 inline-block text-ink">Explore collection →</span>
                </a>
            @endforeach
        </div>
    </section>
@endsection
