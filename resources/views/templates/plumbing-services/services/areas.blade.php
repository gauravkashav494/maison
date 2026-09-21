@extends('layouts.app', ['appBar' => ['title' => 'Service areas', 'back' => true]])

@section('content')
<x-page-head title="Plumbing services near you" text="Local teams in every city below. Pick yours for response times, localities covered and what customers there say." :breadcrumbs="['Service areas' => null]" />
<div class="ps-container py-5 lg:py-10">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">@foreach($areas as $a)<x-area-card :area="$a" />@endforeach</div>
    <p class="mt-5 text-center text-sm text-slate">Not listed? <a href="{{ template()->contact()['phone_href'] }}" class="font-bold text-primary">Call us</a> — we may still be able to help nearby.</p>
</div>
<x-cta-band />
@endsection