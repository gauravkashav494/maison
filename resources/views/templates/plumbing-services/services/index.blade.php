@extends('layouts.app', ['appBar' => ['title' => $q !== '' ? 'Search results' : 'All services', 'back' => $q !== '']])

@section('content')
@php $problems = collect(tsetting('home.problems', []))->filter(fn ($p) => ! empty($p['label'])); $biz = template()->contact(); @endphp
<x-page-head :title="$q !== '' ? 'Results for “'.$q.'”' : 'Plumbing services'" :text="$q !== '' ? $services->count().' matching '.Str::plural('service', $services->count()) : 'Every job we handle — from a dripping tap to a full pipe replacement. Book online, call or WhatsApp.'" :breadcrumbs="['Services' => null]">
    <form action="{{ route('services.index') }}" method="get" role="search" class="flex max-w-xl gap-2">
        <label class="flex h-12 flex-1 items-center gap-2 rounded-full bg-white px-4 ring-1 ring-line focus-within:ring-2 focus-within:ring-bright"><x-ico name="search" :size="18" class="text-primary" /><input type="search" name="q" value="{{ $q }}" placeholder="{{ tsetting('site.search_placeholder', 'Search plumbing services') }}" class="min-w-0 flex-1 bg-transparent text-sm focus:outline-none" aria-label="Search services"></label>
        <button type="submit" class="btn btn-primary">Search</button>
    </form>
</x-page-head>

<div class="ps-container py-5 lg:py-10">
    @if($q === '' && $problems->isNotEmpty())
        <div class="no-scrollbar -mx-4 mb-5 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:mb-8 lg:flex-wrap lg:px-0">
            @foreach($problems as $p)<a href="{{ route('services.problem', str($p['label'])->slug()) }}" class="chip shrink-0"><x-ico :name="$p['icon'] ?? 'help'" :size="14" class="text-primary" /> {{ $p['label'] }}</a>@endforeach
        </div>
    @endif

    @if($services->isEmpty())
        <x-empty-state icon="search" title="No matching service" text="Describe the problem and we will send the right plumber — or call us and we will figure it out together.">
            <a href="{{ route('booking.create', ['problem' => $q]) }}" class="btn btn-primary">Book with this problem</a>
            <a href="{{ $biz['phone_href'] }}" class="btn btn-outline"><x-ico name="phone" :size="16" /> Call us</a>
        </x-empty-state>
    @else
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:gap-5 xl:grid-cols-4">
            @foreach($services as $s)<x-service-card :service="$s" />@endforeach
        </div>
    @endif
</div>

<x-cta-band class="mt-4 lg:mt-10" />
<x-sticky-actions />
@endsection