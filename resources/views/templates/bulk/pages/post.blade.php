@extends('layouts.app')

{{-- One news post or guide. Replaces the shared magazine-style article. --}}

@section('content')
<div class="bk-wrap mt-6">
    <x-page-head :title="$post->title" :text="$post->excerpt" :crumbs="['News & guides' => route('journal.index'), $post->title => null]" />
    <p class="bk-muted mt-2 text-sm">{{ collect([$post->category, $post->published_at?->format('d M Y'), $post->read_time])->filter()->implode(' · ') }}</p>

    <article class="bk-card mt-5 p-5 sm:p-8">
        @if($post->image_url)<img src="{{ $post->image_url }}" alt="" class="mb-6 w-full rounded-lg object-cover" style="max-height: 24rem">@endif
        <div class="bk-prose">{!! $post->body !!}</div>
    </article>

    @if($more->isNotEmpty())
        <section class="mt-8">
            <div class="bk-section-head"><div><h2>More to read</h2></div><a href="{{ route('journal.index') }}" class="bk-link text-sm">All →</a></div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach($more as $p)
                    <a href="{{ $p->url }}" class="bk-card bk-tile-link p-4">
                        <span class="block font-extrabold">{{ $p->title }}</span>
                        @if($p->excerpt)<span class="bk-muted mt-1 block text-sm">{{ $p->excerpt }}</span>@endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
