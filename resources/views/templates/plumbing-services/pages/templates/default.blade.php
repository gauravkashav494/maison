@extends('layouts.app', ['appBar' => ['title' => $page->title, 'back' => true]])

@section('content')
<x-page-head :title="$page->title" :text="$page->excerpt" :eyebrow="$page->eyebrow" :breadcrumbs="[$page->title => null]" />
<section class="ps-container py-6 lg:py-12">
    @if($page->image_url)<img src="{{ $page->image_url }}" alt="" class="mb-6 aspect-[21/9] w-full rounded-3xl object-cover">@endif
    <div class="prose-p mx-auto max-w-3xl text-base">{!! $page->body !!}</div>
</section>
@endsection