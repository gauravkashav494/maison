@extends('layouts.app')

@section('content')
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="p-container py-6">
    @if($page->body)<div class="prose-p mb-6 max-w-3xl text-sm">{!! $page->body !!}</div>@endif
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($stores as $s)
            <div class="card overflow-hidden">
                <div class="aspect-[16/9] bg-sky">@if($s->image_url)<img src="{{ $s->image_url }}" alt="" class="img-cover" loading="lazy">@endif</div>
                <div class="p-4">
                    <h2 class="font-display text-lg font-bold">{{ $s->name }}</h2>
                    <p class="mt-1 text-sm text-slate">{{ $s->address }}, {{ $s->city }} {{ $s->postal_code }}</p>
                    @if($s->hours)<p class="mt-2 whitespace-pre-line text-xs text-slate"><x-ico name="clock" :size="12" class="mr-1 inline text-primary" />{{ $s->hours }}</p>@endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($s->maps_url)<a href="{{ $s->maps_url }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><x-ico name="pin" :size="14" /> Directions</a>@endif
                        @if($s->phone)<a href="tel:{{ preg_replace('/\s+/', '', $s->phone) }}" class="btn btn-ghost btn-sm"><x-ico name="phone" :size="14" /> Call</a>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endsection
