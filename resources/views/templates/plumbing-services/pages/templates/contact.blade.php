@extends('layouts.app', ['appBar' => ['title' => 'Contact', 'back' => false]])

@section('content')
@php $biz = template()->contact(); $services = \App\Models\Service::active()->get(['id', 'name']); $areasList = \App\Models\ServiceArea::active()->get(['name']); @endphp
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="ps-container grid gap-6 py-5 lg:grid-cols-12 lg:gap-10 lg:py-10">
    <div class="space-y-3 lg:col-span-5 lg:order-2">
        <a href="{{ $biz['phone_href'] }}" class="option"><span class="svc-ico h-12 w-12"><x-ico name="phone" :size="22" /></span><span class="flex-1"><span class="block font-display text-sm font-extrabold">Call us</span><span class="block text-sm text-slate">{{ $biz['phone'] }}</span></span><x-ico name="chevron-right" :size="18" class="text-mist" /></a>
        @if($biz['whatsapp'])<a href="{{ $biz['whatsapp_href'] }}" target="_blank" rel="noopener" class="option"><span class="svc-ico svc-ico-whatsapp h-12 w-12"><x-ico name="whatsapp" :size="22" /></span><span class="flex-1"><span class="block font-display text-sm font-extrabold">WhatsApp</span><span class="block text-sm text-slate">Send photos or a video of the problem</span></span><x-ico name="chevron-right" :size="18" class="text-mist" /></a>@endif
        @if($biz['email'])<a href="{{ $biz['email_href'] }}" class="option"><span class="svc-ico h-12 w-12"><x-ico name="mail" :size="22" /></span><span class="flex-1"><span class="block font-display text-sm font-extrabold">Email</span><span class="block text-sm text-slate">{{ $biz['email'] }}</span></span><x-ico name="chevron-right" :size="18" class="text-mist" /></a>@endif
        <div class="card p-4">
            @if($biz['address'])<p class="flex items-start gap-3 text-sm"><span class="svc-ico h-10 w-10 shrink-0"><x-ico name="map-pin" :size="18" /></span><span><span class="block font-display font-extrabold">Address</span><span class="text-slate">{{ $biz['address'] }}</span></span></p>@endif
            @if($biz['hours'])<p class="mt-3 flex items-start gap-3 text-sm"><span class="svc-ico h-10 w-10 shrink-0"><x-ico name="clock" :size="18" /></span><span><span class="block font-display font-extrabold">Working hours</span><span class="text-slate">{{ $biz['hours'] }}</span>@if($biz['emergency_available'])<span class="block font-semibold text-danger">Emergency line open 24×7</span>@endif</span></p>@endif
        </div>
        @if($page->image_url)<img src="{{ $page->image_url }}" alt="" loading="lazy" class="hidden aspect-[4/3] w-full rounded-3xl object-cover lg:block">@endif
    </div>
    <div class="lg:col-span-7 lg:order-1">
        <form method="post" action="{{ route('services.contact') }}" class="card space-y-4 p-4 sm:p-6" x-data="leadForm()" @submit.prevent="submit()">
            @csrf
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            @if(session('contact_status'))<p class="flex items-center gap-2 rounded-xl bg-success-light px-4 py-3 text-sm font-semibold text-success"><x-ico name="check" :size="16" /> {{ session('contact_status') }}</p>@endif
            <p class="font-display text-lg font-extrabold">Send us a message</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label" for="name">Name</label><input id="name" name="name" required value="{{ old('name', auth()->user()?->name) }}" class="field @error('name') field-error @enderror" autocomplete="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="phone">Mobile number</label><input id="phone" name="phone" type="tel" inputmode="tel" required value="{{ old('phone', auth()->user()?->phone) }}" class="field @error('phone') field-error @enderror" autocomplete="tel">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="email">Email <span class="font-normal text-slate">(optional)</span></label><input id="email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" class="field @error('email') field-error @enderror" autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="service">Service</label><select id="service" name="service" class="field"><option value="">General enquiry</option>@foreach($services as $s)<option @selected(old('service', request('service')) === $s->name)>{{ $s->name }}</option>@endforeach</select></div>
                <div class="sm:col-span-2"><label class="label" for="location">Location</label><input id="location" name="location" list="areas" value="{{ old('location') }}" class="field" placeholder="City / locality" autocomplete="off"><datalist id="areas">@foreach($areasList as $a)<option value="{{ $a->name }}">@endforeach</datalist></div>
            </div>
            <div><label class="label" for="message">Message</label><textarea id="message" name="message" rows="5" required class="field @error('message') field-error @enderror" placeholder="Tell us what is happening and when you would like the plumber to visit.">{{ old('message') }}</textarea>@error('message')<p class="error">{{ $message }}</p>@enderror</div>
            <button type="submit" class="btn btn-primary btn-lg btn-block sm:w-auto" :disabled="submitting"><span x-show="!submitting">Send message</span><span x-show="submitting" x-cloak>Sending…</span></button>
        </form>
        @if($page->body)<div class="card prose-p mt-4 p-5">{!! $page->body !!}</div>@endif
        @if(!empty($faqs) && $faqs->isNotEmpty())
            <div class="mt-6"><h2 class="sec-title mb-3 text-lg">Common questions</h2><x-faq-list :faqs="$faqs" /><a href="/faq" class="sec-link mt-3 inline-flex">All FAQs <x-ico name="chevron-right" :size="14" /></a></div>
        @endif
    </div>
</section>
@endsection