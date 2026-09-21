@extends('layouts.app', ['appBar' => ['title' => 'About us', 'back' => true]])

@section('content')
@php $d = $page->data ?? []; $site = tsetting('site'); $biz = template()->contact(); @endphp
<section class="relative overflow-hidden bg-deep text-white">
    @if($page->image_url)<img src="{{ $page->image_url }}" alt="" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover opacity-25">@endif
    <div class="absolute inset-0 bg-gradient-to-r from-deep via-deep/85 to-deep/40"></div>
    <div class="ps-container relative py-10 lg:py-20">
        <x-breadcrumbs :items="[$page->title => null]" class="mb-3 [&_a]:text-white/80 [&_span]:text-white" />
        @if($page->eyebrow)<p class="eyebrow text-accent">{{ $page->eyebrow }}</p>@endif
        <h1 class="mt-2 max-w-3xl font-display text-3xl font-extrabold leading-tight text-balance lg:text-5xl">{{ $page->title }}</h1>
        @if($page->excerpt)<p class="mt-4 max-w-2xl text-sm text-white/85 lg:text-lg">{{ $page->excerpt }}</p>@endif
        @if(!empty($site['stats']))
            <ul class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach(array_slice($site['stats'], 0, 4) as $stat)<li class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/20"><span class="block font-display text-2xl font-extrabold lg:text-3xl">{{ $stat['value'] }}</span><span class="text-xs text-white/80 lg:text-sm">{{ $stat['label'] }}</span></li>@endforeach
            </ul>
        @endif
    </div>
</section>

<section class="ps-container space-y-6 py-6 lg:space-y-10 lg:py-14">
    @if($page->body)<div class="prose-p mx-auto max-w-3xl text-base">{!! $page->body !!}</div>@endif

    @foreach($d['sections'] ?? [] as $i => $s)
        @php $img = \App\Support\Media::url($s['image'] ?? null); $flip = ($s['layout'] ?? 'text-image') === 'image-text'; @endphp
        <div class="card grid overflow-hidden lg:grid-cols-2">
            <div class="{{ $flip ? 'lg:order-2' : '' }} p-6 sm:p-8 lg:p-12">
                @if(!empty($s['eyebrow']))<p class="eyebrow">{{ $s['eyebrow'] }}</p>@endif
                <h2 class="mt-2 font-display text-2xl font-extrabold lg:text-3xl">{{ $s['heading'] ?? '' }}</h2>
                <div class="prose-p mt-3">{!! $s['body'] ?? '' !!}</div>
            </div>
            <div class="min-h-56 bg-sky {{ $flip ? 'lg:order-1' : '' }}">@if($img)<img src="{{ $img }}" alt="" class="img-cover" loading="lazy" decoding="async">@endif</div>
        </div>
    @endforeach

    @if(!empty($d['values']))
        <div>
            <x-section-head title="What we stand for" eyebrow="Our promise" />
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
                @foreach($d['values'] as $i => $v)
                    <div class="tile p-5"><span class="step-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><p class="mt-3 font-display text-base font-extrabold">{{ $v['title'] ?? '' }}</p><p class="mt-1 text-sm leading-relaxed text-slate">{{ $v['text'] ?? '' }}</p></div>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($d['sustainability_heading']))
        <div class="band-deep grid overflow-hidden rounded-3xl lg:grid-cols-2">
            <div class="p-6 sm:p-8 lg:p-12"><p class="eyebrow text-accent">Water matters</p><h2 class="mt-2 font-display text-2xl font-extrabold lg:text-3xl">{{ $d['sustainability_heading'] }}</h2><div class="prose-p mt-3 text-white/85 [&_*]:text-white/85">{!! $d['sustainability_body'] ?? '' !!}</div></div>
            <div class="min-h-56">@if($img = \App\Support\Media::url($d['sustainability_image'] ?? null))<img src="{{ $img }}" alt="" class="img-cover" loading="lazy" decoding="async">@endif</div>
        </div>
    @endif

    @if(!empty($d['founder_quote']))
        <blockquote class="card mx-auto max-w-3xl p-6 text-center sm:p-10"><x-ico name="quote" :size="28" class="mx-auto text-sky-dark" /><p class="mt-3 font-display text-xl font-bold leading-snug lg:text-2xl">“{{ $d['founder_quote'] }}”</p>@if(!empty($d['founder_name']))<footer class="mt-3 text-sm text-slate">— {{ $d['founder_name'] }}</footer>@endif</blockquote>
    @endif
</section>
<x-cta-band heading="Ready when you are." />
@endsection