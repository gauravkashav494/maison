@extends('layouts.app')

@section('content')
<x-auth-shell title="Choose a new password">
    <form method="post" action="{{ route('password.new') }}" class="space-y-4" novalidate>
        @csrf
        <x-countdown :seconds="$secondsLeft">
            <x-slot:expired>Time is up. <a href="{{ route('password.request') }}">Verify your number again</a> to reset your password.</x-slot:expired>
        </x-countdown>
        <label class="block"><span class="label">New password<span class="req" aria-hidden="true">*</span></span><input name="password" type="password" autofocus class="field @error('password') field-error @enderror" autocomplete="new-password"><x-password-checklist />@error('password')<span class="error-text">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="label">Confirm new password<span class="req" aria-hidden="true">*</span></span><input name="password_confirmation" type="password" class="field" autocomplete="new-password"><x-password-match /></label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Save new password</button>
    </form>
</x-auth-shell>
@endsection
