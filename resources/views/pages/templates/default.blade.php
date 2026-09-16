@extends('layouts.app', ['transparentHeader' => filled($page->image_url ?: setting('site.page_header_image'))])

@section('content')
    <x-page-hero :eyebrow="$page->eyebrow" :title="$page->title" :description="$page->excerpt" :image="$page->image_url" :breadcrumbs="[$page->title => null]" />

    <section class="container-luxe py-16 lg:py-24">
        <div class="prose-luxe mx-auto max-w-2xl text-[1.0625rem]">{!! $page->body !!}</div>
        @if(!empty($page->data['cta_label']))
            <div class="mx-auto mt-10 max-w-2xl"><a href="{{ $page->data['cta_url'] ?? '#' }}" class="btn btn-primary btn-lg">{{ $page->data['cta_label'] }}</a></div>
        @endif
    </section>
@endsection
