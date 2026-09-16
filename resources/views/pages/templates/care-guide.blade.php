@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
<x-page-hero :eyebrow="$page->eyebrow" :title="$page->title" :description="$page->excerpt" :breadcrumbs="[$page->title => null]" />

<section class="container-luxe py-12 lg:py-20">
    @if($page->body)<div class="prose-luxe mb-14 max-w-2xl">{!! $page->body !!}</div>@endif
    <div class="space-y-16 lg:space-y-24">
        @foreach($page->data['materials'] ?? [] as $i => $m)
            @php $img = \App\Support\Media::url($m['image'] ?? null); $flip = $i % 2 === 1; @endphp
            <div class="grid items-center gap-8 lg:grid-cols-12 lg:gap-16">
                <div class="{{ $flip ? 'lg:order-2 lg:col-span-5 lg:col-start-8' : 'lg:col-span-5' }}">
                    <div class="img-reveal relative aspect-[4/5] bg-sand" x-data x-intersect.once.threshold.25="$el.classList.add('is-visible')">
                        <div class="img-reveal-clip">@if($img)<img src="{{ $img }}" alt="{{ $m['name'] }}" loading="lazy" class="img-cover absolute inset-0">@endif</div>
                    </div>
                </div>
                <div class="{{ $flip ? 'lg:order-1 lg:col-span-6' : 'lg:col-span-6 lg:col-start-7' }}">
                    <p class="eyebrow text-taupe">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</p>
                    <h2 class="display-md mt-3">{{ $m['name'] }}</h2>
                    <ul class="mt-6 divide-y divide-ink/10 border-y border-ink/10">
                        @foreach($m['tips'] ?? [] as $tip)<li class="flex gap-4 py-3.5 text-[0.9375rem] text-smoke"><span class="mt-2.5 h-1 w-1 shrink-0 rounded-full bg-gold"></span>{{ $tip }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-20 border border-ink/10 bg-cream p-8 text-center">
        <p class="font-serif text-2xl">Repairs, for life.</p>
        <p class="mx-auto mt-2 max-w-md text-sm text-smoke">Every piece can be returned to us for repair for as long as you own it. Contact client care to arrange collection.</p>
        <a href="/contact" class="btn btn-outline mt-6">Arrange a repair</a>
    </div>
</section>
@endsection
