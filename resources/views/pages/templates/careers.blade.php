@extends('layouts.app', ['transparentHeader' => filled($page->image_url ?: setting('site.page_header_image'))])

@section('content')
<x-page-hero :eyebrow="$page->eyebrow" :title="$page->title" :description="$page->excerpt" :image="$page->image_url" :breadcrumbs="[$page->title => null]" />

<section class="container-luxe py-16 lg:py-24">
    <div class="grid gap-14 lg:grid-cols-12 lg:gap-20">
        <div class="lg:col-span-7">
            @if($page->body)<div class="prose-luxe mb-12">{!! $page->body !!}</div>@endif
            <p class="eyebrow text-taupe">Open roles</p>
            @php $roles = $page->data['roles'] ?? []; @endphp
            @if(empty($roles))
                <p class="mt-4 text-sm text-smoke">There are no open roles right now. We keep every application on file — write to us below.</p>
            @else
                <ul class="mt-4 divide-y divide-ink/10 border-y border-ink/10" x-data="accordion()">
                    @foreach($roles as $i => $r)
                        <li>
                            <button type="button" @click="toggle({{ $i }})" class="flex w-full items-center justify-between gap-6 py-6 text-left">
                                <span><span class="font-serif text-2xl">{{ $r['title'] }}</span><span class="mt-1 block text-[0.625rem] uppercase tracking-[0.2em] text-taupe">{{ $r['location'] ?? '' }}@if(!empty($r['type'])) · {{ $r['type'] }}@endif</span></span>
                                <x-ico name="plus" :size="16" class="shrink-0 text-taupe" x-show="open !== {{ $i }}" /><x-ico name="minus" :size="16" class="shrink-0 text-taupe" x-show="open === {{ $i }}" x-cloak />
                            </button>
                            <div x-show="open === {{ $i }}" x-collapse x-cloak>
                                <p class="max-w-xl pb-2 text-[0.9375rem] leading-relaxed text-smoke">{{ $r['summary'] ?? '' }}</p>
                                <a href="{{ $r['apply_url'] ?? 'mailto:careers@maisonelan.com?subject='.rawurlencode('Application: '.$r['title']) }}" class="btn btn-outline btn-sm mb-6 mt-4">Apply</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <aside class="lg:col-span-4 lg:col-start-9">
            @if(!empty($page->data['perks']))
                <p class="eyebrow text-taupe">What we offer</p>
                <ul class="mt-4 space-y-3 text-sm">@foreach($page->data['perks'] as $perk)<li class="flex gap-3"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-gold"></span>{{ $perk }}</li>@endforeach</ul>
            @endif
            <div class="mt-10 border border-ink/10 bg-cream p-6">
                <p class="font-serif text-xl">Don't see your role?</p>
                <p class="mt-2 text-sm text-smoke">Tell us what you do exceptionally well.</p>
                <a href="mailto:careers@maisonelan.com" class="link-underline eyebrow mt-4 inline-block">careers@maisonelan.com</a>
            </div>
        </aside>
    </div>
</section>
@endsection
