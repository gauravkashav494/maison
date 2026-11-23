@extends('layouts.app', ['appBar' => ['title' => 'Log in', 'back' => true]])

@section('content')
<x-auth-shell title="Log in with WhatsApp" text="No password needed. We will send a one-time code to your WhatsApp.">
    <form method="post" action="{{ route('login.otp') }}" class="space-y-4" novalidate>
        @csrf
        <div><label class="label" for="phone">Phone number<span class="req" aria-hidden="true">*</span></label><input id="phone" name="phone" type="tel" inputmode="tel" autofocus value="{{ old('phone') }}" class="field @error('phone') field-error @enderror" autocomplete="tel">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Send code</button>
    </form>
    <p class="mt-5 text-center text-sm text-slate"><a href="{{ route('login') }}" class="font-bold text-primary hover:underline">Log in with password instead</a></p>
</x-auth-shell>
@endsection
