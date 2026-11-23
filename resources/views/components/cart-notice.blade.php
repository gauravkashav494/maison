{{--
    A short message from the cart, e.g. "You can order at most 5 of this item." or "Only 3 left in
    stock.", for the templates that share resources/js/storefront.js (default and Bulk); the other
    templates show it in their own toast. Neutral inline styling so it fits either template.
--}}
<div x-data x-show="$store.ui.notice" x-cloak x-transition.opacity role="status" aria-live="polite"
     style="position:fixed;left:50%;bottom:1.5rem;transform:translateX(-50%);z-index:100;max-width:calc(100vw - 2rem);padding:0.75rem 1rem;border-radius:0.5rem;background:#1f1d1a;color:#fff;font-size:0.875rem;line-height:1.4;box-shadow:0 10px 30px -10px rgba(0,0,0,.4)"
     x-text="$store.ui.notice"></div>
