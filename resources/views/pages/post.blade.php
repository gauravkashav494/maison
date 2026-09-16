@extends('layouts.app', ['transparentHeader' => true])

@section('content')
    <x-page-hero :eyebrow="($post->category ? $post->category . ' · ' : '') . $post->read_time" :title="$post->title" :description="$post->excerpt" :image="$post->image_url" :breadcrumbs="['Journal' => route('journal.index'), $post->title => null]" />

    <article class="container-luxe py-16 lg:py-24">
        <p class="mx-auto max-w-2xl text-[0.625rem] uppercase tracking-[0.22em] text-taupe">{{ $post->published_at?->format('d F Y') }}</p>
        <div class="prose-luxe mx-auto mt-6 max-w-2xl text-[1.0625rem]">{!! $post->body !!}</div>
    </article>

    @if($more->isNotEmpty())
        <section class="border-t border-ink/10 bg-cream">
            <div class="container-luxe py-16 lg:py-24">
                <x-section-header eyebrow="Keep reading" title="More from the journal" cta="All stories" :cta-url="route('journal.index')" />
                <div class="mt-10 grid gap-8 md:grid-cols-2">
                    @foreach($more as $p)
                        <a href="{{ $p->url }}" class="group grid grid-cols-[35%_1fr] gap-5">
                            <div class="relative aspect-[3/4] overflow-hidden bg-sand"><img src="{{ $p->image_url }}" alt="{{ $p->title }}" loading="lazy" class="img-cover img-zoom"></div>
                            <div class="flex flex-col justify-center">
                                <p class="text-[0.625rem] uppercase tracking-[0.22em] text-taupe">{{ $p->category }}</p>
                                <h3 class="mt-2 font-serif text-2xl leading-tight"><span class="link-underline">{{ $p->title }}</span></h3>
                                <p class="mt-2 text-sm text-smoke">{{ $p->excerpt }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
