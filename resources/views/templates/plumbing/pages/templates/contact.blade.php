@extends('layouts.app')

@section('content')
@php $g = tsetting('site'); $subjects = $page->data['subjects'] ?? ['Bulk quote', 'Product question', 'Order issue', 'Return or replacement', 'Other']; $phone = $g['support_phone'] ?? ($site['contact_phone'] ?? null); @endphp
<x-page-head :title="$page->title" :text="$page->excerpt" :breadcrumbs="[$page->title => null]" />
<section class="p-container grid gap-6 py-6 lg:grid-cols-12">
    <div class="lg:col-span-7">
        <div class="card p-5 sm:p-6">
            @if(session('contact_status'))<p class="mb-4 flex items-center gap-2 rounded-xl bg-sky px-4 py-3 text-sm font-semibold text-primary"><x-ico name="check" :size="16" /> {{ session('contact_status') }}</p>@endif
            <p class="font-display text-lg font-bold">Send us a message</p>
            <form method="post" action="{{ route('contact.store') }}" class="mt-4 space-y-4">
                @csrf
                <div class="hidden" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label><span class="label">Name</span><input name="name" required value="{{ old('name', auth()->user()?->name) }}" class="field @error('name') field-error @enderror">@error('name')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label><span class="label">Email</span><input name="email" type="email" required value="{{ old('email', auth()->user()?->email) }}" class="field @error('email') field-error @enderror">@error('email')<span class="error-text">{{ $message }}</span>@enderror</label>
                    <label><span class="label">Phone (optional)</span><input name="phone" type="tel" value="{{ old('phone') }}" class="field"></label>
                    <label><span class="label">Topic</span><select name="subject" class="field">@foreach($subjects as $s)<option @selected(old('subject', request('subject')) === $s)>{{ $s }}</option>@endforeach</select></label>
                </div>
                <label class="block"><span class="label">Message</span><textarea name="message" rows="5" required class="field @error('message') field-error @enderror" placeholder="Include your order number if it’s about an order.">{{ old('message') }}</textarea>@error('message')<span class="error-text">{{ $message }}</span>@enderror</label>
                <button type="submit" class="btn btn-primary btn-lg">Send message</button>
            </form>
        </div>
    </div>
    <div class="space-y-4 lg:col-span-5">
        <div class="card p-5">
            <p class="text-sm font-extrabold">Quick help</p>
            <ul class="mt-3 space-y-3 text-sm">
                @if($phone)<li class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-lg bg-sky text-primary"><x-ico name="phone" :size="18" /></span><span><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="font-bold">{{ $phone }}</a><span class="block text-xs text-slate">{{ $g['support_hours'] ?? '' }}</span></span></li>@endif
                @if(!empty($g['whatsapp_number']))<li class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-lg bg-sky text-primary"><x-ico name="whatsapp" :size="18" /></span><a href="https://wa.me/{{ preg_replace('/\D/', '', $g['whatsapp_number']) }}" class="font-bold">Chat on WhatsApp</a></li>@endif
                @if(!empty($site['contact_email']))<li class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-lg bg-sky text-primary"><x-ico name="mail" :size="18" /></span><a href="mailto:{{ $site['contact_email'] }}" class="font-bold">{{ $site['contact_email'] }}</a></li>@endif
                <li class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-lg bg-sky text-primary"><x-ico name="package" :size="18" /></span><a href="{{ route('track') }}" class="font-bold">Track an order</a></li>
            </ul>
        </div>
        @if($page->body)<div class="card prose-p p-5 text-sm">{!! $page->body !!}</div>@endif
        @if(!empty($faqs) && $faqs->isNotEmpty())
            <div class="card p-5" x-data="accordion()">
                <p class="text-sm font-extrabold">Common questions</p>
                <ul class="mt-2 divide-y divide-line">
                    @foreach($faqs as $f)
                        <li><button type="button" @click="toggle({{ $f->id }})" class="flex w-full items-center justify-between gap-3 py-3 text-left text-sm font-semibold"><span>{{ $f->question }}</span><x-ico name="chevron-down" :size="16" class="shrink-0 text-mist transition-transform" ::class="open === {{ $f->id }} && 'rotate-180'" /></button><div x-show="open === {{ $f->id }}" x-collapse x-cloak class="prose-p pb-3 text-sm">{!! $f->answer !!}</div></li>
                    @endforeach
                </ul>
                <a href="/faq" class="sec-link mt-2 text-xs">All FAQs <x-ico name="chevron-right" :size="14" /></a>
            </div>
        @endif
    </div>
</section>
@endsection
