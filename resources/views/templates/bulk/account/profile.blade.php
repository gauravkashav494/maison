@extends('layouts.app')

@section('content')
<x-account-shell title="Profile & password">
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="post" action="{{ route('account.profile.update') }}" class="card space-y-3 p-4 sm:p-5">
            @csrf @method('PUT')
            <p class="text-sm font-extrabold">Personal details</p>
            <label class="block"><span class="label">Full name</span><input name="name" required value="{{ old('name', $user->name) }}" class="field @error('name') field-error @enderror">@error('name')<span class="error-text">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="label">Email</span><input name="email" type="email" required value="{{ old('email', $user->email) }}" class="field @error('email') field-error @enderror">@error('email')<span class="error-text">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="label">Phone</span><input name="phone" type="tel" value="{{ old('phone', $user->phone) }}" class="field"></label>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
        <form method="post" action="{{ route('account.password.update') }}" class="card space-y-3 p-4 sm:p-5">
            @csrf @method('PUT')
            <p class="text-sm font-extrabold">Change password</p>
            <label class="block"><span class="label">Current password</span><input name="current_password" type="password" required class="field @error('current_password') field-error @enderror" autocomplete="current-password">@error('current_password')<span class="error-text">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="label">New password</span><input name="password" type="password" required class="field @error('password') field-error @enderror" autocomplete="new-password">@error('password')<span class="error-text">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="label">Confirm new password</span><input name="password_confirmation" type="password" required class="field" autocomplete="new-password"></label>
            <button type="submit" class="btn btn-outline">Update password</button>
        </form>
    </div>
</x-account-shell>
@endsection
