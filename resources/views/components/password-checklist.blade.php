@props(['assets' => true])
{{--
    The password rules as one line of warning text under a new-password field, e.g.
    "⚠ 8+ characters, a letter, a number". Each rule turns to success text as soon as it is met,
    and the leading icon becomes a check-circle once all are. It follows the nearest new-password
    input before it in the page (see partials/password-checklist-assets). The rules come from
    App\Support\PasswordRules, the same rule the server checks. The confirm field gets
    <x-password-match>. Spans only: on the storefront it sits inside the field's <label>.
--}}
@php($checks = \App\Support\PasswordRules::checks())
<span class="pw-hint pw-rules" role="list" aria-label="Password requirements" data-password-checklist wire:ignore>
    @include('partials.password-hint-icons')
    <span class="pw-rule-text">
        @foreach ($checks as $check)
            <span class="pw-rule" role="listitem" aria-label="{{ $check['label'] }}, not met yet" data-label="{{ $check['label'] }}" data-pattern="{{ $check['pattern'] }}">{{ $check['label'] }}</span>@if (! $loop->last)<span class="pw-sep" aria-hidden="true">, </span>@endif
        @endforeach
    </span>
</span>
@if ($assets)
    @once
        @include('partials.password-checklist-assets')
    @endonce
@endif
