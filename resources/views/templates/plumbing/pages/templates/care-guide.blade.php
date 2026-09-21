@extends('layouts.app')

@section('content')
{{-- Guide by item (shared page type): each entry has a name, image and tips. --}}
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="p-container py-6">
    <div class="mx-auto max-w-4xl space-y-4">
        @if($page->body)<div class="card prose-p p-5 text-sm">{!! $page->body !!}</div>@endif
        @foreach($page->data['materials'] ?? [] as $m)
            @php $img = \App\Support\Media::url($m['image'] ?? null); @endphp
            <div class="card grid overflow-hidden sm:grid-cols-[12rem_1fr]">
                <div class="min-h-40 bg-sky">@if($img)<img src="{{ $img }}" alt="" class="img-cover" loading="lazy">@endif</div>
                <div class="p-5">
                    <h2 class="font-display text-lg font-bold">{{ $m['name'] ?? '' }}</h2>
                    <ul class="mt-2 space-y-1.5 text-sm text-slate">@foreach($m['tips'] ?? [] as $tip)<li class="flex gap-2"><x-ico name="check" :size="16" class="mt-0.5 shrink-0 text-primary" /> {{ $tip }}</li>@endforeach</ul>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endsection
