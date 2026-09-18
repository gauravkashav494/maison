@extends('layouts.app')

@section('content')
<x-auth-shell title="Forgot your password?" text="Enter your email and we will send you a reset link.">
    <form method="post" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <label class="block"><span class="label">Email</span><input name="email" type="email" required autofocus value="{{ old('email') }}" class="field @error('email') field-error @enderror">@error('email')<span class="error-text">{{ $message }}</span>@enderror</label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Send reset link</button>
    </form>
    <p class="mt-5 text-center text-sm text-muted"><a href="{{ route('login') }}" class="font-bold text-red hover:underline">Back to login</a></p>
</x-auth-shell>
@endsection
