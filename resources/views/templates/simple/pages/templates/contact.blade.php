@extends('layouts.app')

{{--
    Contact page for the Simple List template.

    The shared contact view is styled for a template with photography and its classes do not
    exist in this template's stylesheet. The field names are the same as the shared form, so
    contact.store sees exactly the same request; only the presentation differs.
--}}

@section('content')
@php
    $subjects = $page->data['subjects'] ?? ['Order enquiry', 'Other'];
    $email = $site['contact_email'] ?? null;
    $phone = $site['contact_phone'] ?? null;
@endphp
<div class="ol-page">
    <div class="ol-head">
        <h1>{{ $page->title }}</h1>
        @if($page->excerpt)<p>{{ $page->excerpt }}</p>@endif
    </div>

    <div class="ol-form">
        @if(session('contact_status'))
            <div class="ol-summary">
                <h2>Message received</h2>
                <p class="ol-note">{{ session('contact_status') }}</p>
            </div>
        @else
            <form method="post" action="{{ route('contact.store') }}" class="ol-steps">
                @csrf
                <div hidden aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                <div class="ol-grid" style="margin-top:0">
                    <label class="ol-field">Name
                        <input name="name" required autocomplete="name" value="{{ old('name', auth()->user()?->name) }}">
                        @error('name')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="ol-field">Email
                        <input name="email" type="email" required autocomplete="email" value="{{ old('email', auth()->user()?->email) }}">
                        @error('email')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="ol-field">Phone (optional)
                        <input name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}">
                        @error('phone')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="ol-field">Subject
                        <select name="subject">
                            @foreach($subjects as $subject)<option @selected(old('subject') === $subject)>{{ $subject }}</option>@endforeach
                        </select>
                    </label>
                    <label class="ol-field is-wide">Message
                        <textarea name="message" rows="6" required>{{ old('message') }}</textarea>
                        @error('message')<span class="ol-error">{{ $message }}</span>@enderror
                    </label>
                </div>
                <div><button type="submit" class="ol-add">Send message</button></div>
            </form>
        @endif

        @if($email || $phone)
            <aside class="ol-summary">
                <h2>Other ways to reach us</h2>
                @if($email)<p class="ol-note"><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
                @if($phone)<p class="ol-note"><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a></p>@endif
            </aside>
        @endif
    </div>

    @if($page->body)<div class="ol-note">{!! $page->body !!}</div>@endif
</div>
@endsection
