@extends('layouts.app')

{{-- Choose a new password after the WhatsApp code, in the store's own layout. Same fields as the shared form. --}}

@section('content')
<x-auth-card heading="Choose a new password">
    <form method="post" action="{{ route('password.new') }}" class="space-y-3" novalidate>
        @csrf
        <x-countdown :seconds="$secondsLeft">
            <x-slot:expired>Time is up. <a href="{{ route('password.request') }}">Verify your number again</a> to reset your password.</x-slot:expired>
        </x-countdown>
        <label class="bk-label">New password<span class="bk-req">*</span>
            <input name="password" type="password" autofocus autocomplete="new-password" class="bk-input"><x-password-checklist />
            @error('password')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <label class="bk-label">Confirm new password<span class="bk-req">*</span>
            <input name="password_confirmation" type="password" autocomplete="new-password" class="bk-input"><x-password-match />
        </label>
        <button type="submit" class="bk-btn bk-btn-primary bk-btn-block !py-3 text-base">Save new password</button>
    </form>
</x-auth-card>
@endsection
