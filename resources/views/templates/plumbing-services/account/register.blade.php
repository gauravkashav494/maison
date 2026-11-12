@extends('layouts.app', ['appBar' => ['title' => 'Create account', 'back' => true]])

@section('content')
<x-auth-shell title="Create your account" text="Faster booking, saved addresses and your full service history.">
    <form method="post" action="{{ route('register') }}" class="space-y-4" novalidate>
        @csrf
        <div><label class="label" for="name">Full name<span class="req" aria-hidden="true">*</span></label><input id="name" name="name" value="{{ old('name') }}" class="field @error('name') field-error @enderror" autocomplete="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="email">Email<span class="req" aria-hidden="true">*</span></label><input id="email" name="email" type="email" value="{{ old('email') }}" class="field @error('email') field-error @enderror" autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="phone">Mobile number</label><input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" class="field" autocomplete="tel">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
        </div>
        <div><label class="label" for="password">Password<span class="req" aria-hidden="true">*</span></label><input id="password" name="password" type="password" class="field @error('password') field-error @enderror" autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="password_confirmation">Confirm password<span class="req" aria-hidden="true">*</span></label><input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password"></div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Create account</button>
        <p class="text-center text-xs text-mist">By signing up you agree to our <a href="/terms" class="underline">Terms</a> and <a href="/privacy" class="underline">Privacy Policy</a>.</p>
    </form>
    <p class="mt-5 text-center text-sm text-slate">Already have an account? <a href="{{ route('login') }}" class="font-bold text-primary hover:underline">Log in</a></p>
</x-auth-shell>
@endsection