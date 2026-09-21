@extends('layouts.app', ['appBar' => ['title' => 'Reset password', 'back' => true]])

@section('content')
<x-auth-shell title="Choose a new password">
    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label class="label" for="email">Email</label><input id="email" name="email" type="email" required value="{{ old('email', $email) }}" class="field @error('email') field-error @enderror">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="password">New password</label><input id="password" name="password" type="password" required class="field @error('password') field-error @enderror" autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" required class="field" autocomplete="new-password"></div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Reset password</button>
    </form>
</x-auth-shell>
@endsection