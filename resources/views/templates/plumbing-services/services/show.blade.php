@extends('layouts.app', ['appBar' => ['title' => $service->name, 'back' => true], 'stickyCta' => true])

@section('content')
@php $biz = template()->contact(); $problems = collect($service->problems ?? []); $included = collect($service->included ?? []); $steps = collect(tsetting('home.how_steps', []))->filter(fn ($s) => ! empty($s['title'])); $why = collect(tsetting('home.why_items', []))->filter(fn ($s) => ! empty($s['title']))->take(4); @endphp

{{-- Hero --}}
<section class="bg-white">
    <div class="ps-container pt-0 lg:pt-8">
        <x-breadcrumbs :items="['Services' => route('services.index'), $service->name => null]" class="mb-4" />
        <div class="grid gap-5 lg:grid-cols-12 lg:items-center lg:gap-10">
            <div class="-mx-4 lg:order-2 lg:col-span-6 lg:mx-0">
                <div class="relative aspect-[16/10] overflow-hidden bg-sky lg:rounded-3xl">
                    @if($service->image_url)<img src="{{ $service->image_url }}" alt="{{ $service->name }}" fetchpriority="high" class="img-cover">@endif
                    @if($service->is_emergency)<span class="pill pill-danger absolute left-4 top-4"><span class="pulse-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span> Available 24×7</span>@endif
                </div>
            </div>
            <div class="lg:order-1 lg:col-span-6">
                <div class="flex items-center gap-3">
                    <span class="svc-ico h-12 w-12 {{ $service->is_emergency ? 'svc-ico-danger' : '' }}"><x-ico :name="$service->icon ?: 'wrench'" :size="24" /></span>
                    <div><p class="eyebrow">{{ $service->is_emergency ? '24×7 emergency service' : 'Professional service' }}</p>@if($rating && $rating->rating_count > 0)<x-rating :value="round($rating->rating_avg)" :label="number_format($rating->rating_avg, 1).' · '.$rating->rating_count.' customer '.Str::plural('review', $rating->rating_count)" class="mt-0.5" />@endif</div>
                </div>
                <h1 class="mt-3 font-display text-[1.75rem] font-extrabold leading-tight lg:text-[2.75rem]">{{ $service->name }}</h1>
                <p class="mt-2 text-sm text-slate lg:text-lg">{{ $service->excerpt }}</p>
                @if($service->price_note)<p class="pill pill-sky mt-3"><x-ico name="rupee" :size="12" /> {{ $service->price_note }}</p>@endif
                <ul class="mt-4 grid grid-cols-2 gap-2 text-xs font-semibold text-slate lg:text-sm">
                    <li class="flex items-center gap-2"><x-ico name="bolt" :size="15" class="text-accent" /> {{ $biz['response'] ?: 'Fast response' }}</li>
                    <li class="flex items-center gap-2"><x-ico name="badge" :size="15" class="text-primary" /> Verified plumbers</li>
                    <li class="flex items-center gap-2"><x-ico name="rupee" :size="15" class="text-primary" /> Estimate before work</li>
                    <li class="flex items-center gap-2"><x-ico name="shield" :size="15" class="text-primary" /> 30-day warranty</li>
                </ul>
                <div class="mt-5 hidden flex-wrap gap-2.5 lg:flex">
                    <a href="{{ $service->book_url }}" class="btn btn-primary btn-lg">{{ $service->is_emergency ? 'Get emergency help' : 'Book now' }}</a>
                    <a href="{{ $service->is_emergency ? $biz['emergency_href'] : $biz['phone_href'] }}" class="btn {{ $service->is_emergency ? 'btn-danger' : 'btn-outline' }} btn-lg"><x-ico name="phone" :size="18" /> Call {{ $service->is_emergency ? $biz['emergency_phone'] : $biz['phone'] }}</a>
                    @if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-lg"><x-ico name="whatsapp" :size="18" /> WhatsApp</a>@endif
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Content --}}
<div class="ps-container grid gap-6 py-6 lg:grid-cols-12 lg:gap-10 lg:py-12">
    <div class="space-y-6 lg:col-span-8 lg:space-y-10">
        @if($service->description)
            <section><h2 class="sec-title mb-3 text-lg lg:text-2xl">About this service</h2><div class="prose-p">{!! $service->description !!}</div></section>
        @endif
        @if($problems->isNotEmpty())
            <section>
                <h2 class="sec-title mb-3 text-lg lg:text-2xl">Common problems we fix</h2>
                <ul class="grid gap-2 sm:grid-cols-2">@foreach($problems as $pr)<li class="flex items-center gap-2.5 rounded-xl bg-white px-3.5 py-2.5 text-sm font-semibold ring-1 ring-line"><span class="svc-ico h-7 w-7 rounded-lg"><x-ico name="check" :size="14" /></span> {{ $pr }}</li>@endforeach</ul>
            </section>
        @endif
        @if($included->isNotEmpty())
            <section>
                <h2 class="sec-title mb-3 text-lg lg:text-2xl">What’s included</h2>
                <ul class="card divide-y divide-line-soft">@foreach($included as $inc)<li class="flex items-center gap-3 px-4 py-3 text-sm"><x-ico name="check-badge" :size="18" class="text-success" /> {{ $inc }}</li>@endforeach</ul>
            </section>
        @endif
        @if($steps->isNotEmpty())
            <section>
                <h2 class="sec-title mb-3 text-lg lg:text-2xl">How it works</h2>
                <div class="relative"><div class="absolute left-[1.35rem] top-2 bottom-8 w-px bg-line"></div>@foreach($steps as $i => $s)<x-step :n="$i + 1" :title="$s['title']" :text="$s['text'] ?? null" class="relative lg:flex-row lg:gap-4" />@endforeach</div>
            </section>
        @endif
        @if($why->isNotEmpty())
            <section>
                <h2 class="sec-title mb-3 text-lg lg:text-2xl">Why choose us</h2>
                <div class="grid gap-3 sm:grid-cols-2">@foreach($why as $it)<div class="tile flex gap-3 p-4"><span class="trust-ico h-10 w-10 shrink-0"><x-ico :name="$it['icon'] ?? 'check'" :size="18" /></span><div><p class="font-display text-sm font-extrabold">{{ $it['title'] }}</p><p class="mt-0.5 text-xs leading-relaxed text-slate">{{ $it['text'] ?? '' }}</p></div></div>@endforeach</div>
            </section>
        @endif
        @if($testimonials->isNotEmpty())
            <section>
                <h2 class="sec-title mb-3 text-lg lg:text-2xl">Customer reviews</h2>
                <div class="rail rail-bleed no-scrollbar lg:grid lg:grid-cols-2 lg:gap-4 lg:overflow-visible lg:p-0 lg:mx-0">@foreach($testimonials as $t)<x-review-card :review="$t" compact class="lg:w-auto" />@endforeach</div>
            </section>
        @endif
        @if($projects->isNotEmpty())
            <section>
                <h2 class="sec-title mb-3 text-lg lg:text-2xl">Recent {{ Str::lower($service->name) }} work</h2>
                <div class="rail rail-bleed no-scrollbar lg:grid lg:grid-cols-2 lg:gap-4 lg:overflow-visible lg:p-0 lg:mx-0">@foreach($projects as $p)<x-project-card :project="$p" compact class="lg:w-auto" />@endforeach</div>
            </section>
        @endif
        @if($faqs->isNotEmpty())
            <section><h2 class="sec-title mb-3 text-lg lg:text-2xl">Questions about {{ Str::lower($service->name) }}</h2><x-faq-list :faqs="$faqs" /></section>
        @endif
        @if($areas->isNotEmpty())
            <section>
                <h2 class="sec-title mb-3 text-lg lg:text-2xl">Available in</h2>
                <div class="flex flex-wrap gap-2">@foreach($areas as $a)<a href="{{ $a->url }}" class="chip"><x-ico name="map-pin" :size="13" class="text-primary" /> {{ $a->name }}</a>@endforeach</div>
            </section>
        @endif
    </div>

    {{-- Desktop sticky booking card --}}
    <aside class="hidden lg:col-span-4 lg:block">
        <div class="sticky top-28 space-y-4">
            <div class="card p-6">
                <p class="font-display text-lg font-extrabold">Book {{ Str::lower($service->name) }}</p>
                <p class="mt-1 text-sm text-slate">Pick a day and a slot; we confirm on call and send a verified plumber.</p>
                <a href="{{ $service->book_url }}" class="btn btn-primary btn-lg btn-block mt-4">{{ $service->is_emergency ? 'Get emergency help' : 'Book now' }}</a>
                <a href="{{ $service->is_emergency ? $biz['emergency_href'] : $biz['phone_href'] }}" class="btn btn-outline btn-block mt-2"><x-ico name="phone" :size="16" /> {{ $service->is_emergency ? $biz['emergency_phone'] : $biz['phone'] }}</a>
                @if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp btn-block mt-2"><x-ico name="whatsapp" :size="16" /> WhatsApp us</a>@endif
                <p class="mt-3 text-center text-xs text-slate">{{ $biz['hours'] }}@if($biz['emergency_available']) · Emergencies 24×7 @endif</p>
            </div>
            @if($related->isNotEmpty())
                <div class="card p-5">
                    <p class="font-display text-sm font-extrabold">Related services</p>
                    <ul class="mt-2 divide-y divide-line-soft">@foreach($related->take(5) as $r)<li><a href="{{ $r->url }}" class="flex items-center gap-3 py-2.5 text-sm font-semibold hover:text-primary"><span class="svc-ico h-8 w-8 rounded-lg"><x-ico :name="$r->icon ?: 'wrench'" :size="16" /></span> {{ $r->name }}</a></li>@endforeach</ul>
                </div>
            @endif
        </div>
    </aside>
</div>

@if($related->isNotEmpty())
    <section class="section pt-0 lg:hidden">
        <div class="ps-container"><x-section-head title="Related services" :link="route('services.index')" /></div>
        <div class="ps-container"><div class="rail rail-bleed no-scrollbar">@foreach($related as $r)<x-service-card :service="$r" compact />@endforeach</div></div>
    </section>
@endif

<x-cta-band class="mb-6 lg:mb-0" />
<x-sticky-actions :book-url="$service->book_url" :book-label="$service->is_emergency ? 'Get help now' : 'Book now'" :emergency="$service->is_emergency" />
@endsection