@extends('layouts.app')

{{--
    Policy pages (privacy, terms, refunds): the text with a contents list built from its h3
    headings, and the date it was last changed. The footer links here.
--}}

@section('content')
@php
    $headings = [];
    $body = preg_replace_callback('/<h3>(.*?)<\/h3>/', function ($m) use (&$headings) {
        $id = 'sec-'.\Illuminate\Support\Str::slug(strip_tags($m[1]));
        $headings[] = [$id, strip_tags($m[1])];

        return '<h3 id="'.$id.'">'.$m[1].'</h3>';
    }, $page->body ?? '');
@endphp
<div class="bk-wrap mt-6">
    <x-page-head :title="$page->title" :text="$page->excerpt" :crumbs="[$page->title => null]" />

    <div class="mt-5 grid items-start gap-5 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="bk-card p-5 text-sm lg:sticky lg:top-4">
            <p class="bk-kicker">Last updated</p>
            <p>{{ $page->updated_at->format('d M Y') }}</p>
            @if($headings)
                <p class="bk-kicker mt-4">On this page</p>
                <ol class="space-y-1.5">@foreach($headings as [$id, $label])<li><a href="#{{ $id }}" class="bk-link font-normal">{{ $label }}</a></li>@endforeach</ol>
            @endif
            @if(! empty($page->data['cta_label']))
                <a href="{{ $page->data['cta_url'] ?? '#' }}" class="bk-btn bk-btn-primary bk-btn-block mt-5">{{ $page->data['cta_label'] }}</a>
            @endif
            <p class="bk-muted mt-5 text-xs">Questions? <a href="{{ url('/contact') }}" class="bk-link">Contact the trade desk</a>.</p>
        </aside>
        <article class="bk-card bk-prose p-5 sm:p-8">{!! $body !!}</article>
    </div>
</div>
@endsection
