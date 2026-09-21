@extends('layouts.app', ['appBar' => ['title' => 'Recent work', 'back' => true]])

@section('content')
<x-page-head title="Recent plumbing work" text="Before and after — real jobs by our team. Drag the handle on each photo to compare." :breadcrumbs="['Recent work' => null]" />
<div class="ps-container py-5 lg:py-10">
    @if($projects->isEmpty())
        <x-empty-state icon="image" title="No projects yet" text="We add photos of completed work regularly." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">@foreach($projects as $p)<x-project-card :project="$p" />@endforeach</div>
        @if($projects->hasPages())<div class="mt-8">{{ $projects->links('vendor.pagination.plumbing-services') }}</div>@endif
    @endif
</div>
<x-cta-band />
@endsection