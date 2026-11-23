{{--
    The toast for the templates that share resources/js/storefront.js (default and Bulk): a title
    ("Couldn’t add this item") and an optional second line ("Only 3 left in stock."), dark for a
    confirmation and red for a problem. Wording in resources/js/toast.js; the other templates show
    the same messages in their own toast. Neutral inline styling so it fits either template.
--}}
@props(['bottom' => '1.5rem'])
{{-- x-show sets and clears `display` on the outer box, so the flex layout is on the inner one. --}}
<div x-data x-show="$store.ui.notice" x-cloak x-transition.opacity role="status" aria-live="polite"
     style="position:fixed;left:50%;bottom:{{ $bottom }};transform:translateX(-50%);z-index:100;width:max-content;max-width:min(28rem, calc(100vw - 2rem))">
  <div :style="{ background: $store.ui.notice?.tone === 'error' ? '#b42318' : '#1f1d1a' }"
       style="display:flex;align-items:flex-start;gap:0.6rem;padding:0.8rem 1rem;border-radius:0.75rem;background:#1f1d1a;color:#fff;font-size:0.875rem;line-height:1.35;box-shadow:0 10px 30px -10px rgba(0,0,0,.4)">
    <svg x-show="$store.ui.notice?.tone === 'error'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;margin-top:0.1rem"><circle cx="12" cy="12" r="9" /><path d="M12 8v4M12 16h.01" /></svg>
    <svg x-show="$store.ui.notice?.tone !== 'error'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;margin-top:0.1rem"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
    <div style="min-width:0">
        <p style="margin:0;font-weight:600" x-text="$store.ui.notice?.title"></p>
        <p x-show="$store.ui.notice?.body" style="margin:0.15rem 0 0;font-size:0.8rem;opacity:.85" x-text="$store.ui.notice?.body"></p>
    </div>
  </div>
</div>
