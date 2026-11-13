/**
 * Phone fields across the storefront: 10 digits, nothing else.
 *
 * Every <input name="phone"> (and any type="tel" field) is limited as the buyer types: only
 * digits, at most 10. A pasted or saved "+91 98765 43210" or "098765 43210" becomes
 * "9876543210" rather than being cut short. The server applies the same rule
 * (StorefrontRequest::normalisePhone and ::phone), so the two always agree.
 *
 * No maxlength attribute is set on purpose: the browser would truncate a pasted
 * "+91 98765 43210" to "+91 98765 " before this script could tidy it.
 *
 * Imported by every storefront bundle. Running twice is harmless.
 */

/** "+91 98765-43210" / "098765 43210" / "919876543210" → "9876543210"; otherwise its digits. */
export function normalisePhone(value) {
    let digits = String(value || '').replace(/\D/g, '');
    if (digits.length === 12 && digits.startsWith('91')) digits = digits.slice(2);
    else if (digits.length === 11 && digits.startsWith('0')) digits = digits.slice(1);
    return digits.slice(0, 10);
}

function tidy(input) {
    const next = normalisePhone(input.value);
    if (next !== input.value) {
        input.value = next;
        // Let frameworks bound to the field (Alpine x-model) see the tidied value.
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function enhance(input) {
    if (input.dataset.phone) return;
    input.dataset.phone = '1';
    input.inputMode = 'numeric';
    input.autocomplete = input.autocomplete || 'tel';
    input.removeAttribute('maxlength');
    if (!input.placeholder) input.placeholder = '10-digit number';
    if (input.value) tidy(input);
    // Capture phase, so the field is tidied before any other input handler reads it.
    input.addEventListener('input', () => tidy(input), true);
}

const SELECTOR = 'input[name="phone"], input[type="tel"]';

function scan(root = document) {
    root.querySelectorAll?.(SELECTOR).forEach(enhance);
}

function start() {
    scan();
    new MutationObserver((changes) => {
        for (const change of changes) {
            change.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) return;
                if (node.matches?.(SELECTOR)) enhance(node);
                else scan(node);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
}

if (!window.__phoneInput) {
    window.__phoneInput = true;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
}
