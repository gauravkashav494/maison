@extends('layouts.app', ['appBar' => ['title' => 'New password', 'back' => true]])

@section('content')
<x-auth-shell title="Choose a new password">
    <form method="post" action="{{ route('password.new') }}" class="space-y-4" novalidate>
        @csrf
        <x-countdown :seconds="$secondsLeft">
            <x-slot:expired>Time is up. <a href="{{ route('password.request') }}">Verify your number again</a> to reset your password.</x-slot:expired>
        </x-countdown>
        <div><label class="label" for="password">New password<span class="req" aria-hidden="true">*</span></label><input id="password" name="password" type="password" autofocus class="field @error('password') field-error @enderror" autocomplete="new-password"><x-password-checklist />@error('password')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="password_confirmation">Confirm new password<span class="req" aria-hidden="true">*</span></label><input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password"><x-password-match /></div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Save new password</button>
    </form>
</x-auth-shell>
@endsection