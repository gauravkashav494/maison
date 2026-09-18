@extends('layouts.app')

@section('content')
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="g-container py-6">
    <div class="mx-auto max-w-3xl">
        @if($page->image_url)<img src="{{ $page->image_url }}" alt="" class="mb-6 aspect-[16/7] w-full rounded-2xl object-cover">@endif
        <div class="card prose-g p-5 sm:p-8">{!! $page->body !!}</div>
    </div>
</section>
@endsection
