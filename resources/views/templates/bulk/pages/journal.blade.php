@extends('layouts.app')

{{-- News and guides (the shared journal), as plain cards. Replaces the shared editorial grid. --}}

@section('content')
<div class="bk-wrap mt-6">
    <x-page-head title="News & guides" :crumbs="['News & guides' => null]" />

    @if($posts->isEmpty())
        <div class="bk-card mt-5 p-10 text-center"><p class="font-extrabold">Nothing published yet</p></div>
    @else
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                <a href="{{ $post->url }}" class="bk-card bk-tile-link">
                    @if($post->image_url)<span class="bk-art !rounded-b-none"><img src="{{ $post->image_url }}" alt="" loading="lazy"></span>@endif
                    <span class="block p-4">
                        <span class="bk-muted block text-xs font-semibold">{{ collect([$post->category, $post->published_at?->format('d M Y')])->filter()->implode(' · ') }}</span>
                        <span class="mt-1 block text-lg font-extrabold leading-snug">{{ $post->title }}</span>
                        @if($post->excerpt)<span class="bk-muted mt-1 block text-sm">{{ $post->excerpt }}</span>@endif
                    </span>
                </a>
            @endforeach
        </div>
        @if($posts->hasPages())<div class="bk-pager mt-8">{{ $posts->links() }}</div>@endif
    @endif
</div>
@endsection
