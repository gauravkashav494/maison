@extends('layouts.app')

{{-- A general content page (about, delivery information…) in the store's plain style. --}}

@section('content')
<div class="bk-wrap mt-6">
    <x-page-head :title="$page->title" :text="$page->excerpt" :crumbs="[$page->title => null]" />
    <article class="bk-card mt-5 p-5 sm:p-8">
        @if($page->image_url)<img src="{{ $page->image_url }}" alt="" class="mb-6 w-full rounded-lg object-cover" style="max-height: 22rem">@endif
        <div class="bk-prose">{!! $page->body !!}</div>
        @if(! empty($page->data['cta_label']))
            <a href="{{ $page->data['cta_url'] ?? '#' }}" class="bk-btn bk-btn-primary mt-6">{{ $page->data['cta_label'] }}</a>
        @endif
    </article>
</div>
@endsection
