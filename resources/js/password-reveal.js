/**
 * Show / hide button for every password field on the storefront.
 *
 * Each template draws its own password inputs (underlined, boxed, rounded…), so rather than
 * repeating a toggle in every view this adds one to each <input type="password"> it finds —
 * including fields that appear later — and fits itself to the field: it keeps the input's
 * outer spacing, leaves room on the right for the button and takes the input's text colour.
 *
 * Imported by every storefront bundle (resources/js/app.js and each template entry that does
 * not import it). Running twice is harmless: a field is only ever enhanced once.
 */

const EYE = '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>';
const EYE_OFF = '<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>';
const icon = (paths) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="20" height="20" aria-hidden="true">${paths}</svg>`;

let uid = 0;

function enhance(input) {
    if (input.dataset.reveal || input.type !== 'password' || !input.parentNode) return;
    input.dataset.reveal = '1';
    if (!input.id) input.id = `pw-reveal-${++uid}`;

    const style = getComputedStyle(input);

    // The wrapper takes the field's place and its outer spacing, so the layout does not move.
    const wrap = document.createElement('span');
    wrap.style.cssText = 'position:relative;display:block;';
    wrap.style.marginTop = style.marginTop;
    wrap.style.marginBottom = style.marginBottom;
    // Inputs report "inline-block" even when they fill the row, so measure: only a field that is
    // visibly narrower than its container stays inline. Hidden fields (no size yet) are full width.
    const parent = input.parentElement;
    if (parent && input.offsetWidth > 0) {
        const ps = getComputedStyle(parent);
        const room = parent.clientWidth - parseFloat(ps.paddingLeft) - parseFloat(ps.paddingRight);
        if (input.offsetWidth < room - 2) {
            wrap.style.display = 'inline-block';
            wrap.style.verticalAlign = 'middle';
        }
    }
    input.style.marginTop = '0';
    input.style.marginBottom = '0';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    // Room for the button, without shrinking a field that already has more.
    const right = parseFloat(style.paddingRight) || 0;
    if (right < 40) input.style.paddingRight = '2.75rem';

    const button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('aria-controls', input.id);
    button.setAttribute('aria-pressed', 'false');
    button.setAttribute('aria-label', 'Show password');
    button.title = 'Show password';
    button.innerHTML = icon(EYE);
    button.style.cssText = [
        'position:absolute', 'top:50%', 'right:.35rem', 'transform:translateY(-50%)',
        'display:grid', 'place-items:center', 'width:2.25rem', 'height:2.25rem',
        'padding:0', 'border:0', 'border-radius:.4rem', 'background:transparent',
        'cursor:pointer', 'opacity:.6', 'line-height:0',
    ].join(';');
    button.style.color = style.color;
    button.addEventListener('mouseenter', () => { button.style.opacity = '1'; });
    button.addEventListener('mouseleave', () => { button.style.opacity = '.6'; });

    button.addEventListener('click', () => {
        const show = input.type === 'password';
        // Keep the caret where it was, so the buyer can carry on typing.
        const [start, end] = [input.selectionStart, input.selectionEnd];
        input.type = show ? 'text' : 'password';
        button.innerHTML = icon(show ? EYE_OFF : EYE);
        const label = show ? 'Hide password' : 'Show password';
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-pressed', String(show));
        button.title = label;
        if (document.activeElement === input || document.activeElement === button) {
            input.focus();
            try { input.setSelectionRange(start, end); } catch { /* not supported for this field */ }
        }
    });

    // Never send a form with the password left visible to the browser's autofill history.
    input.form?.addEventListener('submit', () => { input.type = 'password'; });

    wrap.appendChild(button);
}

function scan(root = document) {
    root.querySelectorAll?.('input[type="password"]:not([data-reveal])').forEach(enhance);
}

function start() {
    scan();
    // Fields drawn later (Alpine templates, modals) get a button too.
    new MutationObserver((changes) => {
        for (const change of changes) {
            change.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) return;
                if (node.matches?.('input[type="password"]')) enhance(node);
                else scan(node);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
}

if (!window.__passwordReveal) {
    window.__passwordReveal = true;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
}
