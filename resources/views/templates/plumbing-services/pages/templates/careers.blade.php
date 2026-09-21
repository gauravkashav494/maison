@extends('layouts.app', ['appBar' => ['title' => 'Careers', 'back' => true]])

@section('content')
@php $roles = $page->data['roles'] ?? []; $perks = $page->data['perks'] ?? []; $biz = template()->contact(); @endphp
<x-page-head :title="$page->title" :text="$page->excerpt" :eyebrow="$page->eyebrow" :breadcrumbs="[$page->title => null]" />
<section class="ps-container grid gap-6 py-5 lg:grid-cols-12 lg:gap-10 lg:py-10">
    <div class="lg:col-span-8">
        @if($page->image_url)<img src="{{ $page->image_url }}" alt="" class="mb-6 aspect-[16/7] w-full rounded-3xl object-cover">@endif
        @if($page->body)<div class="prose-p mb-6">{!! $page->body !!}</div>@endif
        <h2 class="sec-title mb-3 text-lg lg:text-2xl">Open roles</h2>
        @if(empty($roles))
            <p class="text-sm text-slate">No open roles right now — write to us and we will keep your profile on file.</p>
        @else
            <ul class="space-y-2.5">
                @foreach($roles as $r)
                    <li class="card flex items-center gap-4 p-4">
                        <span class="svc-ico h-11 w-11"><x-ico name="wrench" :size="20" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-display text-base font-extrabold">{{ $r['title'] }}</span><span class="text-xs text-slate">{{ $r['location'] ?? '' }}@if(!empty($r['type'])) · {{ $r['type'] }}@endif</span></span>
                        <a href="{{ $r['apply_url'] ?? $biz['email_href'].'?subject='.rawurlencode('Application: '.$r['title']) }}" class="btn btn-soft btn-sm">Apply</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <aside class="lg:col-span-4">
        @if($perks)<div class="card p-5"><p class="font-display text-sm font-extrabold">Why work with us</p><ul class="mt-3 space-y-2 text-sm text-slate">@foreach($perks as $perk)<li class="flex gap-2"><x-ico name="check" :size="16" class="mt-0.5 shrink-0 text-primary" /> {{ $perk }}</li>@endforeach</ul></div>@endif
    </aside>
</section>
@endsection