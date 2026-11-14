{{--
    Overrides Filament's user avatar. Filament's default fetches a dark initials image from
    ui-avatars.com (fixed colours, and the name leaves the platform). Without an uploaded avatar
    this draws the initials locally instead, styled by the admin theme for light and dark mode.
--}}
@props([
    'user' => filament()->auth()->user(),
    'size' => 'md',
])

@php
    $name = filament()->getUserName($user);
    $alt = __('filament-panels::layout.avatar.alt', ['name' => $name]);
    $uploaded = $user instanceof \Filament\Models\Contracts\HasAvatar ? $user->getFilamentAvatarUrl() : null;
@endphp

@if (filled($uploaded))
    <x-filament::avatar
        :src="$uploaded"
        :alt="$alt"
        :size="$size"
        :attributes="\Filament\Support\prepare_inherited_attributes($attributes)->class(['fi-user-avatar'])"
    />
@else
    <span
        role="img"
        aria-label="{{ $alt }}"
        {{ $attributes->except(['loading', 'src', 'alt'])->class([
            'fi-avatar fi-circular fi-user-avatar fi-initials-avatar',
            match ($size) { 'sm', 'md', 'lg' => "fi-size-{$size}", default => $size },
        ]) }}
    >{{ \App\Support\Initials::for($name) }}</span>
@endif
