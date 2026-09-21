@extends('layouts.app', ['appBar' => ['title' => $post->category ?: 'Guide', 'back' => true]])

@section('content')
@php $biz = template()->contact(); @endphp
<article class="ps-container py-5 lg:py-12">
    <div class="mx-auto max-w-3xl">
        <x-breadcrumbs :items="['Blog' => route('journal.index'), $post->title => null]" class="mb-4" />
        <span class="pill pill-sky">{{ $post->category }}</span>
        <h1 class="mt-3 font-display text-2xl font-extrabold leading-tight lg:text-4xl">{{ $post->title }}</h1>
        <p class="mt-2 text-sm text-slate">{{ $post->published_at?->format('d M Y') }}@if($post->read_time) · {{ $post->read_time }} min read @endif</p>
        @if($post->image_url)<img src="{{ $post->image_url }}" alt="" fetchpriority="high" class="mt-5 aspect-[16/9] w-full rounded-3xl object-cover">@endif
        @if($post->excerpt)<p class="mt-5 font-display text-lg font-bold leading-snug text-ink">{{ $post->excerpt }}</p>@endif
        <div class="prose-p mt-4 text-base">{!! $post->body !!}</div>
        <div class="band-deep mt-8 flex flex-col gap-3 rounded-3xl p-5 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="font-display text-lg font-extrabold">Need a hand with this?</p><p class="text-sm text-white/85">A verified plumber can be at your door today.</p></div>
            <div class="flex gap-2"><a href="{{ route('booking.create') }}" class="btn btn-accent">Book a plumber</a><a href="{{ $biz['phone_href'] }}" class="btn btn-glass"><x-ico name="phone" :size="16" /> Call</a></div>
        </div>
        @if($more->isNotEmpty())
            <h2 class="sec-title mt-10 mb-3 text-lg">More guides</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach($more as $r)
                    <a href="{{ $r->url }}" class="post-card card card-hover overflow-hidden"><span class="block aspect-[16/10] overflow-hidden bg-sky">@if($r->image_url)<img src="{{ $r->image_url }}" alt="" loading="lazy" class="img-cover">@endif</span><span class="block p-3 font-display text-sm font-extrabold leading-snug line-clamp-2">{{ $r->title }}</span></a>
                @endforeach
            </div>
        @endif
    </div>
</article>
@endsection