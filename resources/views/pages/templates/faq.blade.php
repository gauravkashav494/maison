@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
<x-page-hero :eyebrow="$page->eyebrow" :title="$page->title" :description="$page->excerpt" :breadcrumbs="[$page->title => null]" />

<section class="container-luxe py-12 lg:py-20" x-data="accordion()">
    <div class="grid gap-12 lg:grid-cols-12 lg:gap-16">
        <aside class="lg:col-span-3">
            <nav class="no-scrollbar -mx-5 flex gap-5 overflow-x-auto px-5 text-[0.6875rem] uppercase tracking-[0.2em] lg:sticky lg:top-28 lg:mx-0 lg:flex-col lg:gap-3 lg:px-0" aria-label="FAQ categories">
                @foreach($faqs as $category => $items)
                    <a href="#{{ \Illuminate\Support\Str::slug($category) }}" class="link-underline shrink-0 pb-0.5 text-smoke hover:text-ink">{{ $category }} <span class="text-[0.5625rem] text-taupe">({{ $items->count() }})</span></a>
                @endforeach
            </nav>
            <div class="mt-10 hidden border border-ink/10 bg-cream p-6 lg:block">
                <p class="font-serif text-xl">Still need help?</p>
                <p class="mt-2 text-sm text-smoke">Our client care team replies within one business day.</p>
                <a href="/contact" class="btn btn-outline btn-sm mt-5">Contact us</a>
            </div>
        </aside>
        <div class="lg:col-span-8">
            @if($page->body)<div class="prose-luxe mb-12">{!! $page->body !!}</div>@endif
            @foreach($faqs as $category => $items)
                <div id="{{ \Illuminate\Support\Str::slug($category) }}" class="scroll-mt-32 {{ $loop->first ? '' : 'mt-14' }}">
                    <h2 class="font-serif text-3xl">{{ $category }}</h2>
                    <div class="mt-4 divide-y divide-ink/10 border-y border-ink/10">
                        @foreach($items as $f)
                            <div>
                                <button type="button" @click="toggle({{ $f->id }})" :aria-expanded="open === {{ $f->id }}" class="flex w-full items-center justify-between gap-6 py-5 text-left">
                                    <span class="font-serif text-xl leading-snug">{{ $f->question }}</span>
                                    <x-ico name="plus" :size="16" class="shrink-0 text-taupe" x-show="open !== {{ $f->id }}" />
                                    <x-ico name="minus" :size="16" class="shrink-0 text-taupe" x-show="open === {{ $f->id }}" x-cloak />
                                </button>
                                <div x-show="open === {{ $f->id }}" x-collapse x-cloak><p class="max-w-2xl pb-6 text-[0.9375rem] leading-relaxed text-smoke">{{ $f->answer }}</p></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqs->flatten()->map(fn ($f) => ['@type' => 'Question', 'name' => $f->question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f->answer]])->values()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@endsection
