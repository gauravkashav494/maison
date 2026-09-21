@extends('layouts.app', ['appBar' => ['title' => 'Tips & guides', 'back' => true]])

@section('content')
@php $featured = $posts->first(); $rest = $posts->slice(1); $cats = $posts->pluck('category')->filter()->unique()->values(); @endphp
<x-page-head title="Tips & guides" text="Plain-language guides from our plumbers — leaks, pressure, drains, pipes and maintenance." :breadcrumbs="['Blog' => null]" />
<section class="ps-container py-5 lg:py-10" x-data="{ cat: '' }">
    @if($cats->isNotEmpty())
        <div class="no-scrollbar -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:mb-8 lg:px-0">
            <button type="button" @click="cat = ''" class="chip shrink-0" :class="!cat && 'chip-active'">All</button>
            @foreach($cats as $c)<button type="button" @click="cat = @js($c)" class="chip shrink-0" :class="cat === @js($c) && 'chip-active'">{{ $c }}</button>@endforeach
        </div>
    @endif
    @if($featured)
        <a href="{{ $featured->url }}" class="post-card card card-hover grid overflow-hidden lg:grid-cols-2" x-show="!cat || cat === @js($featured->category)">
            <span class="block aspect-[16/9] overflow-hidden bg-sky lg:aspect-auto">@if($featured->image_url)<img src="{{ $featured->image_url }}" alt="" fetchpriority="high" class="img-cover">@endif</span>
            <span class="block p-5 lg:p-10">
                <span class="pill pill-accent">Featured</span>
                <span class="mt-3 block font-display text-xl font-extrabold leading-snug lg:text-3xl">{{ $featured->title }}</span>
                <span class="mt-2 block text-sm text-slate">{{ $featured->excerpt }}</span>
                <span class="mt-4 block text-xs font-semibold text-primary">{{ $featured->category }} · {{ $featured->read_time ? $featured->read_time.' min read' : $featured->published_at?->format('d M Y') }}</span>
            </span>
        </a>
    @endif
    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:mt-6 lg:grid-cols-3 lg:gap-5">
        @foreach($rest as $post)
            <a href="{{ $post->url }}" class="post-card card card-hover flex gap-3 overflow-hidden p-3 sm:flex-col sm:p-0" x-show="!cat || cat === @js($post->category)">
                <span class="block h-24 w-28 shrink-0 overflow-hidden rounded-xl bg-sky sm:h-auto sm:w-full sm:rounded-none sm:aspect-[16/10]">@if($post->image_url)<img src="{{ $post->image_url }}" alt="" loading="lazy" decoding="async" class="img-cover">@endif</span>
                <span class="block min-w-0 sm:p-4">
                    <span class="pill pill-sky">{{ $post->category }}</span>
                    <span class="mt-1.5 block font-display text-sm font-extrabold leading-snug line-clamp-2 lg:text-base">{{ $post->title }}</span>
                    <span class="mt-1 hidden text-xs text-slate line-clamp-2 sm:block">{{ $post->excerpt }}</span>
                    <span class="mt-1.5 block text-xs text-slate">{{ $post->read_time ? $post->read_time.' min read' : $post->published_at?->format('d M Y') }}</span>
                </span>
            </a>
        @endforeach
    </div>
    @if($posts->hasPages())<div class="mt-8">{{ $posts->links('vendor.pagination.plumbing-services') }}</div>@endif
</section>
<x-cta-band />
@endsection