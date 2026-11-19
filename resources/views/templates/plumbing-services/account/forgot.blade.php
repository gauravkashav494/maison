@extends('layouts.app', ['appBar' => ['title' => 'Forgot password', 'back' => true]])

@section('content')
<x-auth-shell title="Forgot your password?" text="Enter the phone number on your account and we will send a code on WhatsApp.">
    <form method="post" action="{{ route('password.send-code') }}" class="space-y-4" novalidate>
        @csrf
        <div><label class="label" for="phone">Phone number<span class="req" aria-hidden="true">*</span></label><input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" autofocus value="{{ old('phone') }}" class="field @error('phone') field-error @enderror">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Send code</button>
    </form>
    <p class="mt-5 text-center text-sm text-slate"><a href="{{ route('login') }}" class="font-bold text-primary hover:underline">Back to login</a></p>
</x-auth-shell>
@endsection