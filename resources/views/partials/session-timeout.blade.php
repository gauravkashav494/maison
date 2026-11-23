{{--
    Session timeout: after config('session.lifetime') minutes (2 hours) with no use — no click,
    typing, form sent or page opened — the visitor is signed out and taken to the sign-in page.
    No alert is shown.

    The clock runs in the browser because background checks (the panel's notification bell polls
    every 30 seconds) keep the server session alive on their own. The last use is shared between
    tabs (localStorage), as the browser shares one session between them. When the server answers
    419 (the session is already gone), the page reloads instead of showing Livewire's browser box;
    a signed-out visitor then lands on the sign-in page.

    Used by both panels (PanelsRenderHook::BODY_END) and every storefront layout.

    @var bool $signedIn     someone is signed in here, so the idle clock runs
    @var ?string $logoutUrl POST target that signs them out
    @var string $loginUrl   where to go afterwards
--}}
<div id="session-timeout"
     data-minutes="{{ (int) config('session.lifetime') }}"
     data-signed-in="{{ $signedIn ? '1' : '0' }}"
     data-logout-url="{{ $logoutUrl ?? '' }}"
     data-login-url="{{ $loginUrl }}"
     hidden></div>
<script>
(() => {
    if (window.SessionTimeout) return;

    const el = document.getElementById('session-timeout');
    const limit = (Number(el.dataset.minutes) || 120) * 60 * 1000;
    const signedIn = el.dataset.signedIn === '1';
    const logoutUrl = el.dataset.logoutUrl;
    const loginUrl = el.dataset.loginUrl || window.location.href;
    const KEY = 'session.last_activity';
    const nativeFetch = window.fetch.bind(window);
    let memory = Date.now();
    let leaving = false;

    const read = () => { try { return Number(localStorage.getItem(KEY)) || memory; } catch { return memory; } };
    const touch = () => {
        memory = Date.now();
        try { localStorage.setItem(KEY, String(memory)); } catch { /* private mode: this tab only */ }
    };
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value || '';

    /** Idle too long: sign out on the server (polling would keep the session alive), then go to sign-in. */
    async function signOut() {
        if (leaving) return;
        leaving = true;
        if (logoutUrl) {
            try {
                await nativeFetch(logoutUrl, { method: 'POST', credentials: 'same-origin', redirect: 'manual', headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' } });
            } catch { /* already gone */ }
        }
        try { localStorage.removeItem(KEY); } catch { /* ignore */ }
        window.location.href = loginUrl;
    }

    /** The server already ended the session: reload, and a signed-out visitor lands on sign-in. */
    function expired() {
        if (leaving) return;
        leaving = true;
        window.location.reload();
    }

    window.SessionTimeout = { expired };

    window.fetch = async (...args) => {
        const response = await nativeFetch(...args);
        if (response.status === 419) expired();
        return response;
    };
    const onLivewire = () => window.Livewire.hook('request', ({ fail }) => fail(({ status, preventDefault }) => {
        if (status === 419) { preventDefault(); expired(); }
    }));
    window.Livewire ? onLivewire() : document.addEventListener('livewire:init', onLivewire);

    if (!signedIn) return;

    // The idle clock: real use only, never background polling.
    touch();
    let last = 0;
    const onUse = () => { const now = Date.now(); if (now - last > 15000) { last = now; touch(); } };
    ['pointerdown', 'keydown', 'submit', 'wheel', 'touchstart'].forEach((e) => document.addEventListener(e, onUse, { passive: true, capture: true }));

    const check = () => { if (Date.now() - read() >= limit) signOut(); };
    setInterval(check, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
    window.addEventListener('focus', check);
})();
</script>
