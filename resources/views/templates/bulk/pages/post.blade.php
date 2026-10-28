@extends('layouts.app')

@section('content')
<div class="p-container hidden pt-4 lg:block"><x-breadcrumbs :items="['Blog' => route('journal.index'), $post->title => null]" /></div>
<article class="p-container py-6">
    <div class="mx-auto max-w-3xl">
        <p class="flex items-center gap-2 text-xs font-semibold text-slate">@if($post->category)<span class="badge badge-out">{{ $post->category }}</span>@endif @if($post->published_at)<span>{{ $post->published_at->format('d M Y') }}</span>@endif @if($post->read_time)<span>· {{ $post->read_time }} min read</span>@endif</p>
        <h1 class="mt-2 font-display text-3xl font-bold leading-tight sm:text-4xl">{{ $post->title }}</h1>
        @if($post->excerpt)<p class="mt-3 text-base text-slate">{{ $post->excerpt }}</p>@endif
        @if($post->image_url)<img src="{{ $post->image_url }}" alt="" class="mt-6 aspect-[16/9] w-full rounded-2xl object-cover">@endif
        <div class="prose-p mt-8">{!! $post->body !!}</div>
    </div>
    @if($more->isNotEmpty())
        <div class="mx-auto mt-12 max-w-3xl">
            <x-section-head title="More to read" :href="route('journal.index')" />
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($more as $p)
                    <a href="{{ $p->url }}" class="card card-hover flex items-center gap-3 p-3"><span class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-sky">@if($p->image_url)<img src="{{ $p->image_url }}" alt="" class="img-cover" loading="lazy">@endif</span><span><span class="block text-sm font-bold">{{ $p->title }}</span><span class="line-clamp-2 text-xs text-slate">{{ $p->excerpt }}</span></span></a>
                @endforeach
            </div>
        </div>
    @endif
</article>
@endsection
