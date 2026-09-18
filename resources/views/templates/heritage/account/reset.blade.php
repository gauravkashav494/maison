@extends('layouts.app')

@section('content')
<x-auth-shell title="Choose a new password">
    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="block"><span class="label">Email</span><input name="email" type="email" required value="{{ old('email', $email) }}" class="field @error('email') field-error @enderror">@error('email')<span class="error-text">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="label">New password</span><input name="password" type="password" required class="field @error('password') field-error @enderror" autocomplete="new-password">@error('password')<span class="error-text">{{ $message }}</span>@enderror</label>
        <label class="block"><span class="label">Confirm new password</span><input name="password_confirmation" type="password" required class="field" autocomplete="new-password"></label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Reset password</button>
    </form>
</x-auth-shell>
@endsection
