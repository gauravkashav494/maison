@extends('layouts.app', ['appBar' => ['title' => $page->title, 'back' => true]])

@section('content')
<x-page-head :title="$page->title" :text="$page->excerpt" eyebrow="Policy" :breadcrumbs="[$page->title => null]" />
<section class="ps-container py-6 lg:py-12">
    <div class="card mx-auto max-w-3xl p-5 sm:p-8">
        <p class="text-xs text-slate">Last updated {{ $page->updated_at->format('d M Y') }}</p>
        <div class="prose-p mt-4">{!! $page->body !!}</div>
    </div>
</section>
@endsection