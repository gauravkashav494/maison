@extends('layouts.app')

@section('content')
@php $d = $page->data ?? []; @endphp
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="g-container space-y-8 py-6">
    @if($page->image_url)<img src="{{ $page->image_url }}" alt="" class="aspect-[21/9] w-full rounded-2xl object-cover">@endif
    @if($page->body)<div class="prose-g mx-auto max-w-3xl text-base">{!! $page->body !!}</div>@endif

    @foreach($d['sections'] ?? [] as $i => $s)
        @php $img = \App\Support\Media::url($s['image'] ?? null); $flip = ($s['layout'] ?? 'text-image') === 'image-text'; @endphp
        <div class="card grid overflow-hidden lg:grid-cols-2">
            <div class="{{ $flip ? 'lg:order-2' : '' }} p-6 sm:p-8 lg:p-10">
                @if(!empty($s['eyebrow']))<p class="text-xs font-bold uppercase tracking-wider text-leaf">{{ $s['eyebrow'] }}</p>@endif
                <h2 class="mt-1 text-2xl font-extrabold">{{ $s['heading'] ?? '' }}</h2>
                <div class="prose-g mt-3 text-sm">{!! $s['body'] ?? '' !!}</div>
            </div>
            <div class="min-h-56 bg-leaf-light {{ $flip ? 'lg:order-1' : '' }}">@if($img)<img src="{{ $img }}" alt="" class="img-cover" loading="lazy">@endif</div>
        </div>
    @endforeach

    @if(!empty($d['values']))
        <div>
            <x-section-head title="What we stand for" />
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($d['values'] as $i => $v)
                    <div class="card p-4"><span class="step-num">{{ $i + 1 }}</span><p class="mt-3 text-sm font-extrabold">{{ $v['title'] ?? '' }}</p><p class="mt-1 text-xs leading-relaxed text-slate">{{ $v['text'] ?? '' }}</p></div>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($d['sustainability_heading']))
        <div class="grid overflow-hidden rounded-2xl bg-leaf text-white lg:grid-cols-2">
            <div class="p-6 sm:p-8 lg:p-10"><p class="text-xs font-bold uppercase tracking-wider text-white/80">Sustainability</p><h2 class="mt-1 text-2xl font-extrabold">{{ $d['sustainability_heading'] }}</h2><div class="prose-g mt-3 text-sm text-white/85 [&_*]:text-white/85">{!! $d['sustainability_body'] ?? '' !!}</div></div>
            <div class="min-h-56">@if($img = \App\Support\Media::url($d['sustainability_image'] ?? null))<img src="{{ $img }}" alt="" class="img-cover" loading="lazy">@endif</div>
        </div>
    @endif

    @if(!empty($d['founder_quote']))
        <blockquote class="card mx-auto max-w-3xl p-6 text-center sm:p-8"><p class="text-xl font-bold leading-snug">“{{ $d['founder_quote'] }}”</p>@if(!empty($d['founder_name']))<footer class="mt-3 text-sm text-slate">— {{ $d['founder_name'] }}</footer>@endif</blockquote>
    @endif
</section>
@endsection
