@extends('layouts.app', ['appBar' => ['title' => 'Forgot password', 'back' => true]])

@section('content')
<x-auth-shell title="Forgot your password?" text="Enter your email and we will send you a reset link.">
    <form method="post" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div><label class="label" for="email">Email</label><input id="email" name="email" type="email" required autofocus value="{{ old('email') }}" class="field @error('email') field-error @enderror">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Send reset link</button>
    </form>
    <p class="mt-5 text-center text-sm text-slate"><a href="{{ route('login') }}" class="font-bold text-primary hover:underline">Back to login</a></p>
</x-auth-shell>
@endsection