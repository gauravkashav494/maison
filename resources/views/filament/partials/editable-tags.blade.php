{{--
    Chips of a TagsInput marked ->extraAttributes(['class' => 'fi-editable-tags']):

    - Rename in place: click a chip, or Tab to it and press Enter, Space or F2. Enter or clicking
      away saves, Escape cancels; a blank or duplicate name puts the old one back. The new list
      replaces the old at the same position, so the form can tell a rename from an add or remove
      (ProductForm::renamedValue).
    - Delete or Backspace on a focused chip removes it, as its × does.
    - × on a value that variants use (the field's data-value-uses) asks in a dialog first.

    Safe to include more than once.
--}}
<style>
    .fi-editable-tags .fi-badge-label { cursor: text; border-radius: .25rem; }
    .fi-editable-tags .fi-badge-label:focus { outline: none; }
    .fi-editable-tags .fi-badge:has(.fi-badge-label:focus-visible) { box-shadow: 0 0 0 2px var(--primary-600, #4f46e5); }
    .fi-editable-tags .fi-badge-label [contenteditable] { outline: none; min-width: 1ch; display: inline-block; }
    .fi-editable-tags .fi-badge.is-editing { box-shadow: 0 0 0 2px var(--primary-500, #6366f1); }
    .fi-editable-tags .fi-badge.is-editing .fi-badge-delete-btn { display: none; }

    /* The "remove a used value" dialog, matching the panel's confirmation modals. The panel's CSS
       reset drops the browser's own centring of <dialog>, so it is centred here. */
    .fi-tag-remove-dialog { position: fixed; inset: 0; margin: auto; width: min(28rem, calc(100vw - 2rem)); height: fit-content; max-height: calc(100dvh - 2rem); overflow: auto; padding: 0; border: 0; border-radius: .75rem; background: #fff; color: var(--gray-950, #030712); box-shadow: 0 20px 25px -5px rgb(0 0 0 / .1), 0 8px 10px -6px rgb(0 0 0 / .1), 0 0 0 1px rgb(3 7 18 / .05); }
    .fi-tag-remove-dialog:not([open]) { display: none; }
    .fi-tag-remove-dialog::backdrop { background: rgb(3 7 18 / .5); }
    .fi-tag-remove-dialog form { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: 1.5rem; text-align: center; }
    .fi-tag-remove-icon { display: grid; place-items: center; width: 3rem; height: 3rem; margin-bottom: .5rem; border-radius: 9999px; background: var(--danger-100, #fee2e2); color: var(--danger-600, #dc2626); }
    .fi-tag-remove-icon svg { width: 1.5rem; height: 1.5rem; }
    .fi-tag-remove-dialog h2 { margin: 0; font-size: 1rem; font-weight: 600; line-height: 1.5rem; overflow-wrap: anywhere; }
    .fi-tag-remove-dialog p { margin: 0; font-size: .875rem; line-height: 1.25rem; color: var(--gray-500, #6b7280); }
    .fi-tag-remove-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; width: 100%; margin-top: 1rem; }
    .fi-tag-remove-actions button { padding: .5rem .75rem; border-radius: .5rem; font-size: .875rem; font-weight: 600; line-height: 1.25rem; cursor: pointer; }
    .fi-tag-remove-actions .is-cancel { background: #fff; color: var(--gray-950, #030712); box-shadow: inset 0 0 0 1px var(--gray-300, #d1d5db); }
    .fi-tag-remove-actions .is-cancel:hover { background: var(--gray-50, #f9fafb); }
    .fi-tag-remove-actions .is-danger { background: var(--danger-600, #dc2626); color: #fff; }
    .fi-tag-remove-actions .is-danger:hover { background: var(--danger-500, #ef4444); }
    .fi-tag-remove-actions button:focus-visible { outline: 2px solid var(--primary-600, #4f46e5); outline-offset: 2px; }
    @media (max-width: 24rem) { .fi-tag-remove-actions { grid-template-columns: 1fr; } }

    .dark .fi-tag-remove-dialog { background: var(--gray-900, #111827); color: #fff; box-shadow: 0 20px 25px -5px rgb(0 0 0 / .4), 0 0 0 1px rgb(255 255 255 / .1); }
    .dark .fi-tag-remove-dialog::backdrop { background: rgb(3 7 18 / .75); }
    .dark .fi-tag-remove-icon { background: rgb(239 68 68 / .15); color: var(--danger-400, #f87171); }
    .dark .fi-tag-remove-dialog p { color: var(--gray-400, #9ca3af); }
    .dark .fi-tag-remove-actions .is-cancel { background: rgb(255 255 255 / .05); color: #fff; box-shadow: inset 0 0 0 1px rgb(255 255 255 / .2); }
    .dark .fi-tag-remove-actions .is-cancel:hover { background: rgb(255 255 255 / .1); }
    .dark .fi-tag-remove-actions .is-danger { background: var(--danger-500, #ef4444); }
    .dark .fi-tag-remove-actions .is-danger:hover { background: var(--danger-400, #f87171); }
</style>
<script>
    (() => {
        if (window.__editableTags) return;
        window.__editableTags = true;

        const LABEL = '.fi-editable-tags .fi-badge-label';
        const DRAG_EVENTS = ['mousedown', 'pointerdown', 'touchstart', 'dragstart'];

        const chipsOf = (root) => [...root.querySelectorAll('.fi-fo-tags-input-tags-ctn > .fi-badge')];
        const indexOf = (root, chip) => chipsOf(root).indexOf(chip);
        const usable = (root) => root && window.Alpine && !root.closest('.fi-disabled');

        // Once Alpine has redrawn the chips, focus the one at `index` (or the last), or the
        // value box when none are left: focus is never dropped onto the page after a change.
        const focusChip = (root, index) => {
            Alpine.nextTick(() => {
                // New chips are made focusable first: the observer below may not have run yet.
                prepare();
                const chips = chipsOf(root);
                const chip = chips[Math.min(index, chips.length - 1)];
                (chip?.querySelector('.fi-badge-label') ?? root.querySelector('input'))?.focus();
            });
        };

        // Filament's chips are plain spans. Each label is made a focusable button, so keyboard users
        // can reach every chip; new chips (added, renamed, redrawn by Livewire) are picked up as they appear.
        const prepare = () => {
            document.querySelectorAll(`${LABEL}:not([data-editable])`).forEach((label) => {
                label.dataset.editable = '';
                label.tabIndex = 0;
                label.setAttribute('role', 'button');
                label.setAttribute('aria-keyshortcuts', 'Enter Delete');
            });
        };
        let queued = false;
        new MutationObserver(() => {
            if (queued) return;
            queued = true;
            queueMicrotask(() => { queued = false; prepare(); });
        }).observe(document.documentElement, { childList: true, subtree: true });
        document.addEventListener('DOMContentLoaded', prepare);
        prepare();

        // The name is read when the chip gets focus: the label's text is filled in by Alpine after
        // the chip appears, and changes when it is renamed.
        document.addEventListener('focusin', (event) => {
            const label = event.target.closest?.(LABEL);
            if (label && label === event.target) {
                label.setAttribute('aria-label', `${label.textContent.trim()}. Press Enter to rename, Delete to remove.`);
            }
        });

        // ---- Rename in place ----------------------------------------------------------------

        const startEdit = (label) => {
            if (label.querySelector('[contenteditable]')) return;
            const root = label.closest('[x-data]');
            const chip = label.closest('.fi-badge');
            const text = label.querySelector('[x-text]');
            if (!usable(root) || !chip || !text) return;

            const data = Alpine.$data(root);
            const index = indexOf(root, chip);
            const old = data.state?.[index];
            if (old === undefined) return;

            // While editing, the label is a text box, not a button.
            label.removeAttribute('role');
            label.removeAttribute('aria-label');
            label.tabIndex = -1;
            text.setAttribute('contenteditable', 'plaintext-only');
            if (text.contentEditable !== 'plaintext-only') text.setAttribute('contenteditable', 'true');
            text.setAttribute('role', 'textbox');
            text.setAttribute('aria-label', `Rename ${old}`);
            chip.classList.add('is-editing');
            text.focus();
            const range = document.createRange();
            range.selectNodeContents(text);
            const selection = getSelection();
            selection.removeAllRanges();
            selection.addRange(range);

            // Selecting text inside the chip must not start dragging it (the chip is a sort handle).
            const stop = (e) => e.stopPropagation();
            DRAG_EVENTS.forEach((type) => text.addEventListener(type, stop));

            let done = false;
            // `refocus`: ended from the keyboard, so focus returns to the chip. Clicking away
            // leaves focus where the click put it.
            const end = (save, refocus) => {
                if (done) return;
                done = true;
                DRAG_EVENTS.forEach((type) => text.removeEventListener(type, stop));
                text.removeEventListener('keydown', onKey);
                text.removeAttribute('contenteditable');
                text.removeAttribute('role');
                text.removeAttribute('aria-label');
                label.setAttribute('role', 'button');
                label.tabIndex = 0;
                chip.classList.remove('is-editing');

                const value = text.textContent.replace(/\s+/g, ' ').trim();
                const taken = data.state.some((tag, i) => i !== index && tag === value);
                if (!save || value === '' || value === old || taken) {
                    text.textContent = old;
                    if (save && taken) data.announce?.(`"${value}" is already in the list.`);
                } else {
                    const state = [...data.state];
                    state[index] = value;
                    data.state = state;
                    data.announce?.(`Renamed ${old} to ${value}.`);
                }
                if (refocus) focusChip(root, index);
            };
            const onKey = (e) => {
                // Enter must not submit the form or add a value from the box above.
                if (e.key === 'Enter' || e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    end(e.key === 'Enter', true);
                }
            };
            text.addEventListener('keydown', onKey);
            text.addEventListener('blur', () => end(true, false), { once: true });
        };

        document.addEventListener('click', (event) => {
            const label = event.target.closest(LABEL);
            if (label) startEdit(label);
        });

        document.addEventListener('keydown', (event) => {
            const label = event.target.closest?.(LABEL);
            // Only the focused chip itself; keys typed while renaming belong to the text.
            if (!label || label !== event.target) return;
            if (['Enter', ' ', 'F2'].includes(event.key)) {
                event.preventDefault();
                event.stopPropagation();
                startEdit(label);
            } else if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                event.stopPropagation();
                label.closest('.fi-badge')?.querySelector('.fi-badge-delete-btn')?.click();
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                // Arrows move between chips.
                const root = label.closest('[x-data]');
                const chips = chipsOf(root);
                const next = chips[indexOf(root, label.closest('.fi-badge')) + (event.key === 'ArrowLeft' ? -1 : 1)];
                if (next) {
                    event.preventDefault();
                    next.querySelector('.fi-badge-label')?.focus();
                }
            }
        });

        // ---- Remove ---------------------------------------------------------------------------

        // × (or Delete on a chip) on a value that variants use asks first: the field's
        // data-value-uses holds the counts, refreshed with each update. Removing it then removes
        // those variants too. Removing from the keyboard keeps focus on the chips.
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.fi-editable-tags .fi-badge-delete-btn');
            if (!button) return;
            const field = button.closest('[data-value-uses]');
            const root = button.closest('[x-data]');
            const chip = button.closest('.fi-badge');
            if (!usable(root) || !chip) return;

            const index = indexOf(root, chip);
            const tag = Alpine.$data(root).state?.[index];
            // detail 0: pressed from the keyboard (or our Delete key), not clicked.
            const fromKeyboard = event.detail === 0;
            let uses = 0;
            try { uses = JSON.parse(decodeURIComponent(field?.dataset.valueUses || '%7B%7D'))[String(tag).trim()] || 0; } catch (e) {}
            if (!uses) {
                // Filament's own handler removes it; then keep focus in the list.
                if (fromKeyboard) focusChip(root, index);
                return;
            }

            // Hold the removal until the dialog answers; Remove then deletes it as × would have.
            event.preventDefault();
            event.stopImmediatePropagation();
            const one = uses === 1;
            confirmRemoval({
                title: `Remove "${tag}"?`,
                body: `${one ? '1 variant uses' : `${uses} variants use`} this value. ${one ? 'It' : 'They'} will be removed too, with ${one ? 'its' : 'their'} SKU, price and stock, when you save the product.`,
                hint: `To keep ${one ? 'it' : 'them'}, close this and click the value to rename it instead.`,
                confirm: one ? 'Remove value and variant' : `Remove value and ${uses} variants`,
            }).then((ok) => {
                if (ok) {
                    Alpine.$data(root).deleteTag(tag);
                    focusChip(root, index);
                } else {
                    // Back to the chip it was asked from.
                    chip.querySelector('.fi-badge-label')?.focus();
                }
            });
        }, true);

        // One shared dialog, built on first use: a native <dialog> traps focus, closes on Escape
        // and dims the page; styled like the panel's own confirmation modals.
        let dialog = null;
        let pending = null;
        const settle = (ok) => {
            if (dialog.open) dialog.close();
            const resolve = pending;
            pending = null;
            resolve?.(ok);
        };
        const confirmRemoval = ({ title, body, hint, confirm }) => new Promise((resolve) => {
            if (!dialog) {
                dialog = document.createElement('dialog');
                dialog.className = 'fi-tag-remove-dialog';
                dialog.setAttribute('aria-labelledby', 'fi-tag-remove-title');
                dialog.setAttribute('aria-describedby', 'fi-tag-remove-body');
                dialog.innerHTML = `
                    <form method="dialog">
                        <div class="fi-tag-remove-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                        </div>
                        <h2 id="fi-tag-remove-title"></h2>
                        <p id="fi-tag-remove-body"></p>
                        <p class="fi-tag-remove-hint"></p>
                        <div class="fi-tag-remove-actions">
                            <button type="submit" value="cancel" class="is-cancel">Cancel</button>
                            <button type="submit" value="remove" class="is-danger"></button>
                        </div>
                    </form>`;
                // The answer is taken from the button, Escape or backdrop click itself, not from the
                // dialog's close event: that one arrives a moment later, so a quick second × could
                // be answered by the first dialog's Cancel.
                dialog.querySelector('form').addEventListener('submit', (e) => {
                    e.preventDefault();
                    settle(e.submitter?.value === 'remove');
                });
                dialog.addEventListener('cancel', (e) => { e.preventDefault(); settle(false); });
                // A click on the dimmed backdrop (the dialog itself, outside its form) cancels.
                dialog.addEventListener('click', (e) => { if (e.target === dialog) settle(false); });
                document.body.appendChild(dialog);
            }
            dialog.querySelector('h2').textContent = title;
            dialog.querySelector('#fi-tag-remove-body').textContent = body;
            dialog.querySelector('.fi-tag-remove-hint').textContent = hint;
            dialog.querySelector('.is-danger').textContent = confirm;
            pending?.(false);
            pending = resolve;
            if (!dialog.open) dialog.showModal();
            // Start on Cancel, so a stray Enter keeps the variants.
            dialog.querySelector('.is-cancel').focus();
        });
    })();
</script>
