@props(['seconds', 'label' => 'Time remaining'])
{{--
    A live countdown box, e.g. "Code expires in 04:59" on the WhatsApp code page or
    "Time remaining 09:59" on "Choose a new password". It counts to a fixed end time, so a tab
    left in the background is still right; it turns amber in the last minute. At zero the
    enclosing form's submit button is disabled and the `expired` slot shows instead. The server
    enforces the limit either way; without JavaScript the starting time still shows.

    <x-countdown :seconds="$left" label="Code expires in">
        <x-slot:expired>This code has expired.</x-slot:expired>
    </x-countdown>
--}}
@php($seconds = max(0, (int) $seconds))
<div class="countdown"
     role="timer" aria-live="off"
     x-data="{
        end: Date.now() + {{ $seconds }} * 1000,
        left: {{ $seconds }},
        get clock() { return String(Math.floor(this.left / 60)).padStart(2, '0') + ':' + String(this.left % 60).padStart(2, '0') },
        tick() {
            this.left = Math.max(0, Math.ceil((this.end - Date.now()) / 1000));
            if (this.left === 0) this.$el.closest('form')?.querySelectorAll('[type=submit]').forEach((b) => b.disabled = true);
        },
     }"
     x-init="tick(); const timer = setInterval(() => { tick(); if (left === 0) clearInterval(timer) }, 1000)"
     :class="{ 'is-ending': left > 0 && left <= 60, 'is-over': left === 0 }">
    <p x-show="left > 0">
        <svg class="countdown-icon" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.25" fill="none" stroke="currentColor" stroke-width="1.5" /><path d="M10 6v4.25l2.75 1.75" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
        <span>{{ $label }}</span>
        <strong class="countdown-clock" x-text="clock">{{ sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60) }}</strong>
    </p>
    <p class="countdown-over" x-show="left === 0" x-cloak>
        {{ $expired ?? 'Time is up.' }}
    </p>
</div>
@once
    <style>
        .countdown { padding: .625rem .875rem; border-radius: .5rem; background: rgba(120, 120, 120, .08); font-size: .875rem; line-height: 1.4; }
        .countdown p { display: flex; align-items: center; gap: .375rem; margin: 0; }
        .countdown p.countdown-over { display: block; }
        .countdown-icon { width: 1rem; height: 1rem; flex: none; opacity: .7; }
        .countdown-clock { margin-left: auto; font-variant-numeric: tabular-nums; font-size: 1rem; }
        .countdown.is-ending { color: #b45309; background: rgba(245, 158, 11, .1); }
        .countdown.is-over { color: #b91c1c; background: rgba(239, 68, 68, .08); }
        .countdown.is-over a { color: inherit; font-weight: 600; text-decoration: underline; }
        [x-cloak] { display: none !important; }
    </style>
@endonce
