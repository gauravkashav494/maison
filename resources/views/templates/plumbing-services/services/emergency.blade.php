@extends('layouts.app', ['appBar' => ['title' => 'Emergency plumbing', 'back' => true], 'stickyCta' => true])

@section('content')
@php $biz = template()->contact(); $site = tsetting('site'); @endphp
<section class="band-deep relative overflow-hidden">
    <div class="absolute -right-10 -top-10 h-56 w-56 rounded-full bg-danger/40 blur-3xl"></div>
    <div class="ps-container relative py-8 lg:py-16">
        <p class="inline-flex items-center gap-2 rounded-full bg-danger px-3 py-1 text-xs font-extrabold uppercase tracking-wider text-white"><span class="pulse-dot inline-block h-1.5 w-1.5 rounded-full bg-white"></span> Open now · 24×7</p>
        <h1 class="mt-4 font-display text-3xl font-extrabold leading-tight text-balance lg:text-5xl">Plumbing emergency? Help is on the way.</h1>
        <p class="mt-3 max-w-2xl text-sm text-white/85 lg:text-lg">{{ $site['emergency_note'] ?? '' }} {{ $biz['response'] }}.</p>
        <div class="mt-6 flex flex-wrap gap-2.5">
            <a href="{{ $biz['emergency_href'] }}" class="btn btn-danger btn-lg"><x-ico name="phone" :size="18" /> Call {{ $biz['emergency_phone'] }}</a>
            <a href="{{ route('booking.create', ['emergency' => 1] + ($service ? ['service' => $service->slug] : [])) }}" class="btn btn-light btn-lg">Request emergency visit</a>
            @if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-lg"><x-ico name="whatsapp" :size="18" /> WhatsApp</a>@endif
        </div>
    </div>
</section>

<div class="ps-container grid gap-6 py-6 lg:grid-cols-2 lg:gap-10 lg:py-14">
    <section>
        <h2 class="sec-title mb-3 text-lg lg:text-2xl">While you wait, do this</h2>
        <ol class="space-y-3">
            @foreach($steps as $i => [$title, $text])
                <li class="card flex gap-4 p-4"><span class="step-num shrink-0">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><div><p class="font-display text-sm font-extrabold">{{ $title }}</p><p class="mt-0.5 text-sm text-slate">{{ $text }}</p></div></li>
            @endforeach
        </ol>
    </section>
    <section>
        <h2 class="sec-title mb-3 text-lg lg:text-2xl">We handle</h2>
        <ul class="grid gap-2 sm:grid-cols-2">
            @foreach(($service?->problems ?: ['Burst or cracked pipe', 'Water flooding the floor', 'Overflowing drain or toilet', 'Main line leak', 'No water supply', 'Geyser leaking']) as $pr)
                <li class="flex items-center gap-2.5 rounded-xl bg-white px-3.5 py-3 text-sm font-semibold ring-1 ring-line"><span class="svc-ico svc-ico-danger h-8 w-8 rounded-lg"><x-ico name="alert" :size="15" /></span> {{ $pr }}</li>
            @endforeach
        </ul>
        <div class="card mt-4 p-5">
            <p class="font-display text-sm font-extrabold">What to expect</p>
            <ul class="mt-2 space-y-2 text-sm text-slate">
                <li class="flex gap-2"><x-ico name="check" :size="16" class="mt-0.5 shrink-0 text-success" /> A dispatcher confirms the address and guides you on the phone.</li>
                <li class="flex gap-2"><x-ico name="check" :size="16" class="mt-0.5 shrink-0 text-success" /> The nearest verified plumber is sent with the tools for the job.</li>
                <li class="flex gap-2"><x-ico name="check" :size="16" class="mt-0.5 shrink-0 text-success" /> A temporary fix stops the damage; the permanent repair is quoted before it starts.</li>
                <li class="flex gap-2"><x-ico name="check" :size="16" class="mt-0.5 shrink-0 text-success" /> Night and holiday visits carry a visit charge we tell you on the call.</li>
            </ul>
        </div>
    </section>
</div>
<x-sticky-actions :book-url="route('booking.create', ['emergency' => 1] + ($service ? ['service' => $service->slug] : []))" book-label="Request visit" emergency />
@endsection