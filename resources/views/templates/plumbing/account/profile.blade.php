@extends('layouts.app')

@section('content')
<x-account-shell title="Profile & password">
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="post" action="{{ route('account.profile.update') }}" class="card space-y-3 p-4 sm:p-5" novalidate>
            @csrf @method('PUT')
            <p class="text-sm font-extrabold">Personal details</p>
            <label class="block"><span class="label">Full name<span class="req" aria-hidden="true">*</span></span><input name="name" value="{{ old('name', $user->name) }}" class="field @error('name') field-error @enderror">@error('name')<span class="error-text">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="label">Email<span class="req" aria-hidden="true">*</span></span><input name="email" type="email" value="{{ old('email', $user->email) }}" class="field @error('email') field-error @enderror">@error('email')<span class="error-text">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="label">Phone</span><input name="phone" type="tel" value="{{ old('phone', $user->phone) }}" class="field">@error('phone')<span class="error-text">{{ $message }}</span>@enderror</label>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
        <form method="post" action="{{ route('account.password.update') }}" class="card space-y-3 p-4 sm:p-5" novalidate>
            @csrf @method('PUT')
            <p class="text-sm font-extrabold">Change password</p>
            <label class="block"><span class="label">Current password<span class="req" aria-hidden="true">*</span></span><input name="current_password" type="password" class="field @error('current_password') field-error @enderror" autocomplete="current-password">@error('current_password')<span class="error-text">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="label">New password<span class="req" aria-hidden="true">*</span></span><input name="password" type="password" class="field @error('password') field-error @enderror" autocomplete="new-password">@error('password')<span class="error-text">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="label">Confirm new password<span class="req" aria-hidden="true">*</span></span><input name="password_confirmation" type="password" class="field" autocomplete="new-password"></label>
            <button type="submit" class="btn btn-outline">Update password</button>
        </form>
    </div>
</x-account-shell>
@endsection
