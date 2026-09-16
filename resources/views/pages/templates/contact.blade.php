@extends('layouts.app', ['transparentHeader' => filled(setting('site.page_header_image'))])

@section('content')
@php $label = 'block text-[0.6875rem] uppercase tracking-[0.2em] text-smoke'; $subjects = $page->data['subjects'] ?? ['Order enquiry', 'Returns', 'Product question', 'Other']; @endphp
<x-page-hero :eyebrow="$page->eyebrow" :title="$page->title" :description="$page->excerpt" :breadcrumbs="[$page->title => null]" />

<section class="container-luxe py-12 lg:py-20">
    <div class="grid gap-14 lg:grid-cols-12 lg:gap-20">
        <div class="lg:col-span-7">
            @if(session('contact_status'))
                <div class="border border-emerald-700/20 bg-emerald-50 p-8 text-center">
                    <p class="font-serif text-2xl">Message received</p>
                    <p class="mt-2 text-sm text-emerald-800">{{ session('contact_status') }}</p>
                </div>
            @else
                <form method="post" action="{{ route('contact.store') }}" class="space-y-6">
                    @csrf
                    <div class="hidden" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <label><span class="{{ $label }}">Name</span><input name="name" required value="{{ old('name', auth()->user()?->name) }}" class="input-luxe">@error('name')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                        <label><span class="{{ $label }}">Email</span><input name="email" type="email" required value="{{ old('email', auth()->user()?->email) }}" class="input-luxe">@error('email')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                        <label><span class="{{ $label }}">Phone (optional)</span><input name="phone" type="tel" value="{{ old('phone') }}" class="input-luxe"></label>
                        <label><span class="{{ $label }}">Subject</span><select name="subject" class="input-luxe">@foreach($subjects as $s)<option @selected(old('subject') === $s)>{{ $s }}</option>@endforeach</select></label>
                    </div>
                    <label class="block"><span class="{{ $label }}">Message</span><textarea name="message" rows="6" required class="input-luxe">{{ old('message') }}</textarea>@error('message')<span class="text-xs text-rouge">{{ $message }}</span>@enderror</label>
                    <button type="submit" class="btn btn-primary btn-lg">Send message <x-ico name="arrow-right" :size="14" class="btn-arrow" /></button>
                </form>
            @endif
            @if($page->body)<div class="prose-luxe mt-14 border-t border-ink/10 pt-10">{!! $page->body !!}</div>@endif
        </div>

        <aside class="space-y-10 lg:col-span-4 lg:col-start-9">
            <div>
                <p class="eyebrow text-taupe">Client care</p>
                <p class="mt-3 font-serif text-2xl"><a href="mailto:{{ $site['contact_email'] ?? '' }}" class="link-underline">{{ $site['contact_email'] ?? '' }}</a></p>
                <p class="mt-2 font-serif text-2xl"><a href="tel:{{ preg_replace('/\s+/', '', $site['contact_phone'] ?? '') }}" class="link-underline">{{ $site['contact_phone'] ?? '' }}</a></p>
                <p class="mt-3 text-sm text-smoke">{{ $site['contact_hours'] ?? '' }}</p>
            </div>
            <div>
                <p class="eyebrow text-taupe">Support</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="/track-order" class="link-underline">Track an order</a></li>
                    <li><a href="/returns" class="link-underline">Returns & exchanges</a></li>
                    <li><a href="/shipping" class="link-underline">Shipping information</a></li>
                    <li><a href="/size-guide" class="link-underline">Size guide</a></li>
                    <li><a href="/stores" class="link-underline">Boutiques</a></li>
                </ul>
            </div>
            <div>
                <p class="eyebrow text-taupe">Follow</p>
                <div class="mt-3 flex gap-5 text-ink/70">
                    @foreach([['instagram', $site['social_instagram'] ?? null], ['pinterest', $site['social_pinterest'] ?? null], ['facebook', $site['social_facebook'] ?? null], ['youtube', $site['social_youtube'] ?? null]] as [$icon, $url])
                        @if($url)<a href="{{ $url }}" target="_blank" rel="noreferrer" class="hover:text-ink" aria-label="{{ $icon }}"><x-ico :name="$icon" :size="18" :stroke="1.5" /></a>@endif
                    @endforeach
                </div>
            </div>
            @if($faqs->isNotEmpty())
                <div class="border border-ink/10 bg-cream p-6">
                    <p class="eyebrow text-taupe">Quick answers</p>
                    <ul class="mt-4 space-y-3 text-sm">@foreach($faqs as $f)<li><a href="/faq#{{ \Illuminate\Support\Str::slug($f->category) }}" class="link-underline">{{ $f->question }}</a></li>@endforeach</ul>
                    <a href="/faq" class="btn btn-outline btn-sm mt-6">Visit the FAQ</a>
                </div>
            @endif
        </aside>
    </div>
</section>
@endsection
