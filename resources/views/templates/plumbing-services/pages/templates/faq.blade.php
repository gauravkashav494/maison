@extends('layouts.app', ['appBar' => ['title' => 'FAQs', 'back' => true]])

@section('content')
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="ps-container py-5 lg:py-10" x-data="{ q: '' }">
    <label class="mx-auto flex h-12 max-w-xl items-center gap-2 rounded-full bg-white px-4 ring-1 ring-line focus-within:ring-2 focus-within:ring-bright"><x-ico name="search" :size="18" class="text-primary" /><input x-model="q" type="search" placeholder="Search questions" class="min-w-0 flex-1 bg-transparent text-sm focus:outline-none" aria-label="Search questions"></label>
    @if($page->body)<div class="prose-p mx-auto mt-4 max-w-3xl text-sm">{!! $page->body !!}</div>@endif
    <div class="mx-auto mt-6 max-w-3xl space-y-6">
        @foreach($faqs as $cat => $items)
            <div x-show="!q || {{ Js::from($items->map(fn ($f) => Str::lower($f->question.' '.strip_tags($f->answer)))) }}.some(t => t.includes(q.toLowerCase()))">
                <h2 class="mb-2.5 font-display text-lg font-extrabold">{{ $cat }}</h2>
                <div class="space-y-2.5">
                    @foreach($items as $f)
                        <details class="faq-item" x-show="!q || @js(Str::lower($f->question.' '.strip_tags($f->answer))).includes(q.toLowerCase())">
                            <summary class="flex items-center justify-between gap-4 px-4 py-3.5"><span class="font-display text-[0.95rem] font-bold leading-snug">{{ $f->question }}</span><span class="faq-chev grid h-7 w-7 shrink-0 place-items-center rounded-full bg-sky text-primary transition-transform"><x-ico name="chevron-down" :size="16" /></span></summary>
                            <div class="prose-p px-4 pb-4 text-sm">{!! nl2br(e(strip_tags($f->answer))) !!}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        @endforeach
        <div class="card flex flex-col items-start gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="font-display text-sm font-extrabold">Still have a question?</p><p class="text-xs text-slate">Call, WhatsApp or send a message — we reply within business hours.</p></div>
            <a href="/contact" class="btn btn-outline btn-sm">Contact us</a>
        </div>
    </div>
</section>
@endsection