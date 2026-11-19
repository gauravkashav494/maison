{{-- Style and behaviour of <x-password-checklist> and <x-password-match>. Safe to include more than once. --}}
<style>
    /* One line of warning text; what is met (each rule, or the whole line) turns to success text. */
    .pw-hint { display: flex; align-items: flex-start; gap: .375rem; margin-top: .5rem; font-size: .8125rem; font-weight: 400; line-height: 1.25rem; text-transform: none; letter-spacing: normal; color: #b45309; transition: color .15s; }
    .pw-hint-icon { width: 1rem; height: 1rem; flex: none; margin-top: .125rem; }
    /* No icon while something is missing; a check-circle once everything is met. */
    .pw-hint:not(.is-ok) .pw-hint-icon, .pw-i-warn { display: none; }
    .pw-rule { transition: color .15s; }
    /* A comma takes the colour of the rule before it. */
    .pw-rule.is-ok, .pw-rule.is-ok + .pw-sep, .pw-hint.is-ok { color: #15803d; }
    /* The match line says nothing until the confirm field has something in it. */
    [data-password-match]:not(.is-shown) { display: none; }

    /* The panels' dark mode (a class on <html>); storefronts are light only. */
    .dark .pw-hint { color: #fbbf24; }
    .dark .pw-rule.is-ok, .dark .pw-rule.is-ok + .pw-sep, .dark .pw-hint.is-ok { color: #4ade80; }
</style>
<script>
    (() => {
        if (window.__passwordChecklist) return;
        window.__passwordChecklist = true;

        const INPUT = 'input[autocomplete="new-password"]';
        const FIELDS = INPUT + ', [data-password-checklist], [data-password-match]';

        // Each rule turns to success text once met; the whole line (and its icon) once all are.
        const paintChecklist = (line, value) => {
            const rules = [...line.querySelectorAll('[data-pattern]')];
            let met = 0;
            rules.forEach((rule) => {
                const ok = new RegExp(rule.dataset.pattern, 'u').test(value);
                met += ok ? 1 : 0;
                rule.classList.toggle('is-ok', ok);
                rule.setAttribute('aria-label', rule.dataset.label + (ok ? ', met' : ', not met yet'));
            });
            line.classList.toggle('is-ok', rules.length > 0 && met === rules.length);
        };

        const paintMatch = (line, confirm, password) => {
            const same = confirm === password;
            line.classList.toggle('is-shown', confirm !== '');
            line.classList.toggle('is-ok', same);
            line.querySelector('[data-match-text]').textContent = same ? 'Passwords match' : 'Passwords do not match';
        };

        // Walk the page in order: a checklist belongs to the new-password input just before it;
        // a match line belongs to the input just before it (the confirm field) and compares it
        // with the input that owns the last checklist (the password field).
        const refreshAll = () => {
            let previous = null;
            let password = null;
            document.querySelectorAll(FIELDS).forEach((el) => {
                if (el.matches('[data-password-checklist]')) {
                    if (previous) paintChecklist(el, previous.value);
                    password = previous;
                } else if (el.matches('[data-password-match]')) {
                    if (previous) paintMatch(el, previous.value, password ? password.value : '');
                } else {
                    previous = el;
                }
            });
        };

        document.addEventListener('input', (event) => {
            if (event.target.matches?.(INPUT)) refreshAll();
        });
        document.addEventListener('DOMContentLoaded', refreshAll);
        document.addEventListener('livewire:navigated', refreshAll);
        // Panel forms re-render after Livewire updates (a modal opening, a repeater row added).
        document.addEventListener('livewire:init', () => {
            window.Livewire.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(refreshAll)));
        });
        if (document.readyState !== 'loading') refreshAll();
    })();
</script>
