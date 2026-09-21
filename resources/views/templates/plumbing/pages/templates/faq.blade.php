@extends('layouts.app')

@section('content')
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="p-container grid gap-6 py-6 lg:grid-cols-12" x-data="{ tab: @js($faqs->keys()->first()), q: '' }">
    <aside class="lg:col-span-3">
        <div class="card p-3 lg:sticky lg:top-[7.5rem]">
            <label class="relative block"><x-ico name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate" /><input x-model="q" type="search" placeholder="Search questions" class="field h-10 pl-9 text-sm"></label>
            <nav class="no-scrollbar mt-3 flex gap-1 overflow-x-auto lg:flex-col" aria-label="FAQ categories">
                @foreach($faqs as $cat => $items)
                    <button type="button" @click="tab = @js($cat); q = ''" class="shrink-0 rounded-lg px-3 py-2 text-left text-sm font-semibold" :class="tab === @js($cat) && !q ? 'bg-sky text-primary' : 'text-slate hover:bg-canvas'">{{ $cat }} <span class="text-xs text-mist">({{ $items->count() }})</span></button>
                @endforeach
            </nav>
        </div>
    </aside>
    <div class="lg:col-span-9">
        @if($page->body)<div class="prose-p mb-4 text-sm">{!! $page->body !!}</div>@endif
        @foreach($faqs as $cat => $items)
            <div x-show="q ? true : tab === @js($cat)" class="card mb-4 p-4 sm:p-5" x-data="accordion()">
                <h2 class="font-display text-lg font-bold">{{ $cat }}</h2>
                <ul class="mt-2 divide-y divide-line">
                    @foreach($items as $f)
                        <li x-show="!q || @js(Str::lower($f->question.' '.strip_tags($f->answer))).includes(q.toLowerCase())">
                            <button type="button" @click="toggle({{ $f->id }})" class="flex w-full items-center justify-between gap-3 py-3 text-left text-sm font-semibold" :aria-expanded="open === {{ $f->id }}"><span>{{ $f->question }}</span><x-ico name="chevron-down" :size="16" class="shrink-0 text-mist transition-transform" ::class="open === {{ $f->id }} && 'rotate-180'" /></button>
                            <div x-show="open === {{ $f->id }}" x-collapse x-cloak class="prose-p pb-4 text-sm">{!! $f->answer !!}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <div class="card flex flex-col items-start gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm font-bold">Still need help?</p><p class="text-xs text-slate">Our support team replies within a few hours.</p></div>
            <a href="/contact" class="btn btn-outline btn-sm">Contact us</a>
        </div>
    </div>
</section>
@endsection
