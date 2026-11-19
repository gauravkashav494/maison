@props(['assets' => true])
{{--
    One line under a "confirm password" field, in the same style as <x-password-checklist>:
    nothing until something is typed, then warning text "Passwords do not match" or success text
    "Passwords match". Compares with the password field whose <x-password-checklist> comes
    before it (see partials/password-checklist-assets).
--}}
<span class="pw-hint" aria-live="polite" data-password-match wire:ignore>
    @include('partials.password-hint-icons')
    <span data-match-text></span>
</span>
@if ($assets)
    @once
        @include('partials.password-checklist-assets')
    @endonce
@endif
