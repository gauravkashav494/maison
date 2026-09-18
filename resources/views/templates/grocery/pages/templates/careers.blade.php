@extends('layouts.app')

@section('content')
@php $roles = $page->data['roles'] ?? []; $perks = $page->data['perks'] ?? []; @endphp
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="g-container grid gap-6 py-6 lg:grid-cols-12">
    <div class="lg:col-span-8">
        @if($page->image_url)<img src="{{ $page->image_url }}" alt="" class="mb-6 aspect-[16/7] w-full rounded-2xl object-cover">@endif
        @if($page->body)<div class="prose-g mb-6 text-sm">{!! $page->body !!}</div>@endif
        <h2 class="text-lg font-extrabold">Open roles</h2>
        @if(empty($roles))
            <p class="mt-2 text-sm text-slate">No open roles right now — write to us and we’ll keep your profile on file.</p>
        @else
            <ul class="mt-3 space-y-3" x-data="accordion()">
                @foreach($roles as $i => $r)
                    <li class="card p-4">
                        <button type="button" @click="toggle({{ $i }})" class="flex w-full items-center justify-between gap-3 text-left">
                            <span><span class="block text-base font-extrabold">{{ $r['title'] }}</span><span class="text-xs text-slate">{{ $r['location'] ?? '' }}@if(!empty($r['type'])) · {{ $r['type'] }}@endif</span></span>
                            <x-ico name="chevron-down" :size="18" class="shrink-0 text-mist transition-transform" ::class="open === {{ $i }} && 'rotate-180'" />
                        </button>
                        <div x-show="open === {{ $i }}" x-collapse x-cloak>
                            <p class="mt-3 text-sm leading-relaxed text-slate">{{ $r['summary'] ?? '' }}</p>
                            <a href="{{ $r['apply_url'] ?? 'mailto:'.(setting('site.contact_email') ?? 'careers@example.com').'?subject='.rawurlencode('Application: '.$r['title']) }}" class="btn btn-primary btn-sm mt-3">Apply now</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <aside class="lg:col-span-4">
        @if($perks)
            <div class="card p-5"><p class="text-sm font-extrabold">Why work with us</p><ul class="mt-3 space-y-2 text-sm text-slate">@foreach($perks as $perk)<li class="flex gap-2"><x-ico name="check" :size="16" class="mt-0.5 shrink-0 text-leaf" /> {{ $perk }}</li>@endforeach</ul></div>
        @endif
    </aside>
</section>
@endsection
