@extends('layouts.app')

@section('content')
<x-account-shell title="Account details">
    <div class="grid items-start gap-5 lg:grid-cols-2">
        <form method="post" action="{{ route('account.profile.update') }}" class="bk-card space-y-4 p-5 sm:p-6" novalidate>
            @csrf @method('PUT')
            <h2 class="text-lg font-extrabold">Your details</h2>
            <label class="bk-label">Full name<span class="bk-req">*</span><input name="name" value="{{ old('name', $user->name) }}" class="bk-input" autocomplete="name">@error('name')<span class="bk-error">{{ $message }}</span>@enderror</label>
            <label class="bk-label">Email<span class="bk-req">*</span><input name="email" type="email" value="{{ old('email', $user->email) }}" class="bk-input" autocomplete="email">@error('email')<span class="bk-error">{{ $message }}</span>@enderror</label>
            <label class="bk-label">Phone<input name="phone" type="tel" value="{{ old('phone', $user->phone) }}" class="bk-input" autocomplete="tel">@error('phone')<span class="bk-error">{{ $message }}</span>@enderror</label>
            <button type="submit" class="bk-btn bk-btn-primary">Save changes</button>
        </form>

        <form method="post" action="{{ route('account.password.update') }}" class="bk-card space-y-4 p-5 sm:p-6" novalidate>
            @csrf @method('PUT')
            <h2 class="text-lg font-extrabold">Change password</h2>
            <label class="bk-label">Current password<span class="bk-req">*</span><input name="current_password" type="password" class="bk-input" autocomplete="current-password">@error('current_password')<span class="bk-error">{{ $message }}</span>@enderror</label>
            <label class="bk-label">New password<span class="bk-req">*</span><input name="password" type="password" class="bk-input" autocomplete="new-password">@error('password')<span class="bk-error">{{ $message }}</span>@enderror</label>
            <label class="bk-label">Confirm new password<span class="bk-req">*</span><input name="password_confirmation" type="password" class="bk-input" autocomplete="new-password"></label>
            <button type="submit" class="bk-btn bk-btn-outline">Update password</button>
        </form>
    </div>
</x-account-shell>
@endsection
