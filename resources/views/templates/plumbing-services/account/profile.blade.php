@extends('layouts.app', ['appBar' => ['title' => 'Profile', 'back' => true]])

@section('content')
<x-account-shell title="Profile & password">
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="post" action="{{ route('account.profile.update') }}" class="card space-y-3 p-4 sm:p-5" novalidate>
            @csrf @method('PUT')
            <p class="font-display text-sm font-extrabold">Personal details</p>
            <div><label class="label" for="name">Full name<span class="req" aria-hidden="true">*</span></label><input id="name" name="name" value="{{ old('name', $user->name) }}" class="field @error('name') field-error @enderror">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="email">Email<span class="req" aria-hidden="true">*</span></label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="field @error('email') field-error @enderror">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="phone">Mobile number</label><input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone', $user->phone) }}" class="field">@error('phone')<p class="error">{{ $message }}</p>@enderror</div>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
        <form method="post" action="{{ route('account.password.update') }}" class="card space-y-3 p-4 sm:p-5" novalidate>
            @csrf @method('PUT')
            <p class="font-display text-sm font-extrabold">Change password</p>
            <div><label class="label" for="current_password">Current password<span class="req" aria-hidden="true">*</span></label><input id="current_password" name="current_password" type="password" class="field @error('current_password') field-error @enderror" autocomplete="current-password">@error('current_password')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="password">New password<span class="req" aria-hidden="true">*</span></label><input id="password" name="password" type="password" class="field @error('password') field-error @enderror" autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="password_confirmation">Confirm new password<span class="req" aria-hidden="true">*</span></label><input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password"></div>
            <button type="submit" class="btn btn-outline">Update password</button>
        </form>
    </div>
</x-account-shell>
@endsection