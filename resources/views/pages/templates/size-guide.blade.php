@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
@php $tables = $page->data['tables'] ?? []; $howTo = $page->data['how_to'] ?? []; @endphp
<x-page-hero :eyebrow="$page->eyebrow" :title="$page->title" :description="$page->excerpt" :breadcrumbs="[$page->title => null]" />

<section class="container-luxe py-12 lg:py-20" x-data="{ tab: 0 }">
    @if($page->body)<div class="prose-luxe mb-12 max-w-2xl">{!! $page->body !!}</div>@endif

    <div class="no-scrollbar -mx-5 flex gap-6 overflow-x-auto border-b border-ink/10 px-5 lg:mx-0 lg:px-0" role="tablist">
        @foreach($tables as $i => $t)
            <button type="button" role="tab" @click="tab = {{ $i }}" :aria-selected="tab === {{ $i }}" class="link-underline shrink-0 pb-3 text-[0.6875rem] uppercase tracking-[0.2em]" :data-active="tab === {{ $i }}" :class="tab === {{ $i }} ? 'text-ink' : 'text-smoke'">{{ $t['name'] }}</button>
        @endforeach
    </div>

    @foreach($tables as $i => $t)
        <div x-show="tab === {{ $i }}" x-cloak class="mt-10 overflow-x-auto">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead><tr class="border-b border-ink text-[0.625rem] uppercase tracking-[0.2em]">@foreach($t['columns'] ?? [] as $col)<th class="py-3 pr-6 font-medium">{{ $col }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-ink/10">
                    @foreach($t['rows'] ?? [] as $row)
                        <tr>@foreach($row as $j => $cell)<td class="py-3 pr-6 tabular-nums {{ $j === 0 ? 'font-medium' : 'text-smoke' }}">{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    @if($howTo)
        <div class="mt-20 border-t border-ink/10 pt-14">
            <x-section-header eyebrow="How to measure" title="Measure once, order with confidence." align="left" />
            <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($howTo as $i => $h)
                    <div><p class="font-serif text-3xl text-taupe">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</p><h3 class="mt-3 text-[0.75rem] font-medium uppercase tracking-[0.18em]">{{ $h['title'] }}</h3><p class="mt-2 text-sm leading-relaxed text-smoke">{{ $h['text'] }}</p></div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-16 border border-ink/10 bg-cream p-8 text-center">
        <p class="font-serif text-2xl">Between sizes?</p>
        <p class="mt-2 text-sm text-smoke">Our client advisors can recommend a fit from your measurements. Exchanges are always complimentary.</p>
        <a href="/contact" class="btn btn-outline mt-6">Ask a stylist</a>
    </div>
</section>
@endsection
