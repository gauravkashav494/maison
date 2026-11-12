@extends('layouts.app', ['appBar' => ['title' => 'Reset password', 'back' => true]])

@section('content')
<x-auth-shell title="Choose a new password">
    <form method="post" action="{{ route('password.update') }}" class="space-y-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label class="label" for="email">Email<span class="req" aria-hidden="true">*</span></label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" class="field @error('email') field-error @enderror">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="password">New password<span class="req" aria-hidden="true">*</span></label><input id="password" name="password" type="password" class="field @error('password') field-error @enderror" autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="password_confirmation">Confirm new password<span class="req" aria-hidden="true">*</span></label><input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password"></div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Reset password</button>
    </form>
</x-auth-shell>
@endsection