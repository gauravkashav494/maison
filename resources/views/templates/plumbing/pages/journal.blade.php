@extends('layouts.app')

@section('content')
<x-page-head title="Blog & guides" text="Buying guides, installation tips and checklists from our plumbing team." :breadcrumbs="['Blog' => null]" />
<section class="p-container py-6">
    @if($posts->isEmpty())
        <x-empty-state icon="sparkle" title="Stories coming soon" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                <a href="{{ $post->url }}" class="card card-hover group overflow-hidden">
                    <div class="aspect-[16/10] overflow-hidden bg-sky">@if($post->image_url)<img src="{{ $post->image_url }}" alt="" class="img-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">@endif</div>
                    <div class="p-4">
                        <p class="flex items-center gap-2 text-xs font-semibold text-slate">@if($post->category)<span class="badge badge-out">{{ $post->category }}</span>@endif @if($post->read_time)<span>{{ $post->read_time }} min read</span>@endif</p>
                        <h2 class="mt-2 font-display text-lg font-bold leading-snug group-hover:text-primary">{{ $post->title }}</h2>
                        <p class="mt-1 line-clamp-2 text-sm text-slate">{{ $post->excerpt }}</p>
                    </div>
                </a>
            @endforeach
        </div>
        @if($posts->hasPages())<div class="mt-8">{{ $posts->links('vendor.pagination.plumbing') }}</div>@endif
    @endif
</section>
@endsection
