/* ---------------------------------------------------------------------------
   App shell — shared by every storefront template.
   Registers the service worker, captures the browser's install prompt so the
   storefront can offer "Add to home screen" itself, and provides the back
   navigation used by the mobile app bar.
--------------------------------------------------------------------------- */
export default function registerAppShell(Alpine) {
    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
    let deferredPrompt = null;

    Alpine.store('app', {
        standalone,
        ios,
        canInstall: false,
        dismissedAt: Alpine.$persist(0).as('app-install-dismissed'),
        visits: Alpine.$persist(0).as('app-visits'),
        /* Offer the install card: Chrome/Android once the browser allows it, iOS (no prompt API)
           from the second visit with a "Share → Add to Home Screen" hint. Snoozed 14 days on dismiss. */
        get showInstall() {
            if (this.standalone) return false;
            if (Date.now() - this.dismissedAt < 14 * 864e5) return false;
            return this.canInstall || (this.ios && this.visits >= 2);
        },
        async install() {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            deferredPrompt = null;
            this.canInstall = false;
            if (outcome !== 'accepted') this.dismiss();
        },
        dismiss() { this.dismissedAt = Date.now(); },
        /* Back arrow in the app bar: real history when we came from this site, otherwise home. */
        back() {
            let sameSite = false;
            try { sameSite = !!document.referrer && new URL(document.referrer).origin === location.origin; } catch {}
            if (window.history.length > 1 && sameSite) window.history.back();
            else window.location.assign('/');
        },
    });

    try {
        if (!sessionStorage.getItem('app-visit-counted')) {
            sessionStorage.setItem('app-visit-counted', '1');
            Alpine.store('app').visits++;
        }
    } catch {}

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        Alpine.store('app').canInstall = true;
    });
    window.addEventListener('appinstalled', () => { Alpine.store('app').canInstall = false; });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => { navigator.serviceWorker.register('/sw.js').catch(() => {}); });
    }
}
