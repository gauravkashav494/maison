@extends('layouts.app', ['transparentHeader' => filled($page->image_url ?: setting('site.page_header_image'))])

@section('content')
@php $d = $page->data ?? []; @endphp
<x-page-hero :eyebrow="$page->eyebrow" :title="$page->title" :description="$page->excerpt" :image="$page->image_url" :breadcrumbs="[$page->title => null]" />

@if($page->body)
    <section class="container-luxe py-16 lg:py-24"><div class="prose-luxe mx-auto max-w-2xl text-[1.0625rem]">{!! $page->body !!}</div></section>
@endif

{{-- Story sections --}}
@foreach($d['sections'] ?? [] as $i => $s)
    @php $img = \App\Support\Media::url($s['image'] ?? null); $flip = ($s['layout'] ?? 'text-image') === 'image-text'; @endphp
    <section class="container-luxe py-16 lg:py-24 {{ $i % 2 ? 'border-t border-ink/10' : '' }}">
        <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-16">
            <div class="{{ $flip ? 'lg:order-2 lg:col-span-6 lg:col-start-7' : 'lg:col-span-6' }}">
                <div class="img-reveal relative aspect-[4/5] bg-sand" x-data x-intersect.once.threshold.25="$el.classList.add('is-visible')">
                    <div class="img-reveal-clip">@if($img)<img src="{{ $img }}" alt="{{ strip_tags($s['heading'] ?? '') }}" loading="lazy" class="img-cover absolute inset-0">@endif</div>
                </div>
            </div>
            <div class="{{ $flip ? 'lg:order-1 lg:col-span-5' : 'lg:col-span-5 lg:col-start-8' }}" x-data x-intersect.once="$el.querySelectorAll('.reveal').forEach(e => e.classList.add('is-visible'))">
                @if(!empty($s['eyebrow']))<p class="reveal eyebrow text-taupe">{{ $s['eyebrow'] }}</p>@endif
                <h2 class="reveal display-md mt-4 text-balance" style="--reveal-delay:.1s">{!! emph($s['heading'] ?? '') !!}</h2>
                <div class="reveal mt-6 space-y-4 text-[0.9375rem] leading-relaxed text-smoke" style="--reveal-delay:.2s">@foreach(preg_split('/\n\s*\n/', $s['body'] ?? '') as $para)<p>{{ $para }}</p>@endforeach</div>
            </div>
        </div>
    </section>
@endforeach

{{-- Values --}}
@if(!empty($d['values']))
    <section class="border-y border-ink/10 bg-cream">
        <div class="container-luxe py-16 lg:py-24">
            <x-section-header eyebrow="What we stand for" title="A few things we will not compromise on." align="left" />
            <div class="mt-12 grid gap-px bg-ink/10 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($d['values'] as $i => $v)
                    <div class="reveal bg-cream p-7" style="--reveal-delay: {{ $i * .06 }}s" x-data x-intersect.once="$el.classList.add('is-visible')">
                        <p class="font-serif text-3xl text-taupe">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</p>
                        <h3 class="mt-4 font-serif text-2xl">{{ $v['title'] ?? '' }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-smoke">{{ $v['text'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- Founder quote --}}
@if(!empty($d['founder_quote']))
    <section class="container-luxe py-20 lg:py-28">
        <blockquote class="mx-auto max-w-3xl text-center">
            <p class="display-md text-balance">“{{ $d['founder_quote'] }}”</p>
            <footer class="eyebrow mt-8 text-taupe">{{ $d['founder_name'] ?? '' }}</footer>
        </blockquote>
    </section>
@endif

{{-- Sustainability --}}
@if(!empty($d['sustainability_heading']))
    @php $simg = \App\Support\Media::url($d['sustainability_image'] ?? null); @endphp
    <section id="sustainability" class="grid lg:grid-cols-2">
        <div class="img-reveal relative aspect-[4/5] bg-sand sm:aspect-[16/11] lg:aspect-auto lg:min-h-[40rem]" x-data x-intersect.once.threshold.25="$el.classList.add('is-visible')">
            <div class="img-reveal-clip">@if($simg)<img src="{{ $simg }}" alt="" loading="lazy" class="img-cover absolute inset-0">@endif</div>
        </div>
        <div class="flex flex-col justify-center bg-charcoal px-6 py-16 text-ivory sm:px-10 lg:px-20 lg:py-24">
            <p class="eyebrow text-gold-light">Responsible fashion</p>
            <h2 class="display-md mt-5 text-balance">{!! emph($d['sustainability_heading']) !!}</h2>
            <div class="mt-7 max-w-md space-y-4 text-[0.9375rem] leading-relaxed text-ivory/70">@foreach(preg_split('/\n\s*\n/', $d['sustainability_body'] ?? '') as $para)<p>{{ $para }}</p>@endforeach</div>
        </div>
    </section>
@endif

{{-- CTA --}}
<section class="container-luxe py-20 text-center lg:py-28">
    <p class="eyebrow text-taupe">The collections</p>
    <h2 class="display-md mt-4">See what we make.</h2>
    <a href="{{ route('collections.index') }}" class="btn btn-primary btn-lg mt-8">Discover Our Collections <x-ico name="arrow-right" :size="14" class="btn-arrow" /></a>
</section>
@endsection
