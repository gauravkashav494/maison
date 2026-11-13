@extends('layouts.app')

{{-- Choose a new password, in the store's own layout. Same fields as the shared form. --}}

@section('content')
<x-auth-card heading="Choose a new password">
    <form method="post" action="{{ route('password.update') }}" class="space-y-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="bk-label">Email<span class="bk-req">*</span>
            <input name="email" type="email" autocomplete="email" class="bk-input" value="{{ old('email', $email) }}">
            @error('email')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <label class="bk-label">New password<span class="bk-req">*</span>
            <input name="password" type="password" autofocus autocomplete="new-password" class="bk-input">
            @error('password')<span class="bk-error">{{ $message }}</span>@enderror
        </label>
        <label class="bk-label">Confirm new password<span class="bk-req">*</span>
            <input name="password_confirmation" type="password" autocomplete="new-password" class="bk-input">
        </label>
        <button type="submit" class="bk-btn bk-btn-primary bk-btn-block !py-3 text-base">Save new password</button>
    </form>
</x-auth-card>
@endsection
