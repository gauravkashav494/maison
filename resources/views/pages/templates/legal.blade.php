@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
@php
    // Build an in-page table of contents from the document's h3 headings.
    $headings = [];
    $body = preg_replace_callback('/<h3>(.*?)<\/h3>/', function ($m) use (&$headings) {
        $id = 'sec-'.\Illuminate\Support\Str::slug(strip_tags($m[1]));
        $headings[] = [$id, strip_tags($m[1])];
        return '<h3 id="'.$id.'">'.$m[1].'</h3>';
    }, $page->body ?? '');
@endphp
<x-page-hero :eyebrow="$page->eyebrow ?? 'Policies'" :title="$page->title" :description="$page->excerpt" :breadcrumbs="[$page->title => null]" />

<section class="container-luxe py-16 lg:py-24">
    <div class="mx-auto grid max-w-5xl gap-12 lg:grid-cols-12">
        <aside class="lg:col-span-4">
            <div class="lg:sticky lg:top-28">
                <p class="eyebrow text-taupe">Last updated</p>
                <p class="mt-2 text-sm">{{ $page->updated_at->format('d F Y') }}</p>
                @if($headings)
                    <p class="eyebrow mt-8 text-taupe">On this page</p>
                    <ol class="mt-3 space-y-2 text-sm">@foreach($headings as [$id, $label])<li><a href="#{{ $id }}" class="link-underline text-smoke hover:text-ink">{{ $label }}</a></li>@endforeach</ol>
                @endif
                @if(!empty($page->data['cta_label']))
                    <a href="{{ $page->data['cta_url'] ?? '#' }}" class="btn btn-primary mt-8 w-full">{{ $page->data['cta_label'] }}</a>
                @endif
                <p class="mt-8 text-xs text-smoke">Questions? <a href="/contact" class="underline underline-offset-4">Contact client care</a>.</p>
            </div>
        </aside>
        <div class="prose-luxe lg:col-span-8">{!! $body !!}</div>
    </div>
</section>
@endsection
