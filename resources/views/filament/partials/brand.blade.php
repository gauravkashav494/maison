{{-- Brand mark for the admin top bar / sidebar --}}
<span style="display:inline-flex;align-items:center;gap:.6rem;">
    <span style="display:grid;place-items:center;width:2rem;height:2rem;border-radius:.6rem;background:rgba(255,255,255,.12);box-shadow:inset 0 0 0 1px rgba(255,255,255,.18);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 6.5L12 21 5 9.5z"/><path d="M5 9.5h14M12 3l-3.5 6.5L12 21l3.5-11.5z"/></svg>
    </span>
    <span style="font-size:1rem;font-weight:800;letter-spacing:-.01em;">{{ setting('site.name', config('app.name')) }}</span>
</span>
