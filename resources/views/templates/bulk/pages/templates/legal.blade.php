@extends('layouts.app')

@section('content')
@php
    $headings = [];
    $body = preg_replace_callback('/<h3>(.*?)<\/h3>/', function ($m) use (&$headings) {
        $id = 'sec-'.\Illuminate\Support\Str::slug(strip_tags($m[1]));
        $headings[] = [$id, strip_tags($m[1])];
        return '<h3 id="'.$id.'">'.$m[1].'</h3>';
    }, $page->body ?? '');
@endphp
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="['Legal' => null, $page->title => null]" />
<section class="p-container grid gap-6 py-6 lg:grid-cols-12">
    @if($headings)
        <aside class="lg:col-span-3">
            <nav class="card p-4 lg:sticky lg:top-[7.5rem]" aria-label="On this page">
                <p class="text-xs font-bold uppercase tracking-wider text-mist">On this page</p>
                <ol class="mt-2 space-y-1.5 text-sm">@foreach($headings as [$id, $label])<li><a href="#{{ $id }}" class="text-slate hover:text-primary">{{ $label }}</a></li>@endforeach</ol>
            </nav>
        </aside>
    @endif
    <div class="{{ $headings ? 'lg:col-span-9' : 'lg:col-span-8 lg:col-start-3' }}">
        <div class="card prose-p p-5 sm:p-8">
            <p class="!mt-0 text-xs text-mist">Last updated {{ $page->updated_at->format('d M Y') }}</p>
            {!! $body !!}
        </div>
    </div>
</section>
@endsection
