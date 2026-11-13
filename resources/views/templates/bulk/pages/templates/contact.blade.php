@extends('layouts.app')

{{--
    Contact page: the trade desk's details beside the shared contact form. Field names match
    the shared form, so contact.store receives exactly the same request.
--}}

@php
    $subjects = $page->data['subjects'] ?? ['Order enquiry', 'Other'];
    $email = tsetting('site.support_email') ?: ($site['contact_email'] ?? null);
    $phone = tsetting('site.support_phone') ?: ($site['contact_phone'] ?? null);
    $hours = tsetting('site.support_hours');
@endphp

@section('content')
<div class="bk-wrap mt-6">
    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $page->title }}</h1>
    @if($page->excerpt)<p class="bk-muted mt-1 max-w-2xl">{{ $page->excerpt }}</p>@endif

    <div class="mt-6 grid items-start gap-5 lg:grid-cols-[20rem_minmax(0,1fr)]">
        <aside class="bk-card space-y-4 p-5">
            <p class="font-extrabold">Trade desk</p>
            @if($phone)
                <p class="flex gap-3"><span class="bk-icon-tile"><x-heroicon-o-phone /></span><span><span class="bk-muted block text-xs">Call</span><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="bk-link">{{ $phone }}</a></span></p>
            @endif
            @if($email)
                <p class="flex gap-3"><span class="bk-icon-tile"><x-heroicon-o-envelope /></span><span><span class="bk-muted block text-xs">Email</span><a href="mailto:{{ $email }}" class="bk-link break-all">{{ $email }}</a></span></p>
            @endif
            @if($hours)
                <p class="flex gap-3"><span class="bk-icon-tile"><x-heroicon-o-clock /></span><span><span class="bk-muted block text-xs">Hours</span>{{ $hours }}</span></p>
            @endif
        </aside>

        <div class="bk-card p-5 sm:p-6">
            @if(session('contact_status'))
                <div class="py-6 text-center">
                    <x-heroicon-o-check-circle class="mx-auto size-12 text-[var(--bk-ok)]" aria-hidden="true" />
                    <p class="mt-3 text-lg font-extrabold">Message received</p>
                    <p class="bk-muted mt-1">{{ session('contact_status') }}</p>
                </div>
            @else
                <form method="post" action="{{ route('contact.store') }}" class="grid gap-4 sm:grid-cols-2" novalidate>
                    @csrf
                    <div hidden aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                    <label class="bk-label">Name<span class="bk-req">*</span>
                        <input name="name" autocomplete="name" class="bk-input" value="{{ old('name', auth()->user()?->name) }}">
                        @error('name')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="bk-label">Email<span class="bk-req">*</span>
                        <input name="email" type="email" autocomplete="email" class="bk-input" value="{{ old('email', auth()->user()?->email) }}">
                        @error('email')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <x-phone-field />
                    <label class="bk-label">Subject
                        <select name="subject" class="bk-select">
                            @foreach($subjects as $subject)<option @selected(old('subject') === $subject)>{{ $subject }}</option>@endforeach
                        </select>
                        @error('subject')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="bk-label sm:col-span-2">Message<span class="bk-req">*</span>
                        <textarea name="message" rows="6" class="bk-textarea">{{ old('message') }}</textarea>
                        @error('message')<span class="bk-error">{{ $message }}</span>@enderror
                    </label>
                    <div class="sm:col-span-2"><button type="submit" class="bk-btn bk-btn-primary">Send message</button></div>
                </form>
            @endif
        </div>
    </div>

    @if($page->body)<div class="bk-card mt-5 p-5 leading-relaxed sm:p-6">{!! $page->body !!}</div>@endif
</div>
@endsection
