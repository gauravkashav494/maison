@extends('layouts.app')

@section('content')
<x-auth-shell title="Log in with WhatsApp" text="No password needed. We will send a one-time code to your WhatsApp.">
    <form method="post" action="{{ route('login.otp') }}" class="space-y-4" novalidate>
        @csrf
        <label class="block"><span class="label">Phone number<span class="req" aria-hidden="true">*</span></span><input name="phone" type="tel" inputmode="tel" autofocus value="{{ old('phone') }}" class="field @error('phone') field-error @enderror" autocomplete="tel">@error('phone')<span class="error-text">{{ $message }}</span>@enderror</label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Send code</button>
    </form>
    <p class="mt-5 text-center text-sm text-muted"><a href="{{ route('login') }}" class="font-bold text-red hover:underline">Log in with password instead</a></p>
</x-auth-shell>
@endsection
