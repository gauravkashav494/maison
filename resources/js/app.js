import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import persist from '@alpinejs/persist';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';
import registerExtras from './storefront-extras';
import registerAppShell from './app-shell';

Alpine.plugin(intersect);
Alpine.plugin(persist);
Alpine.plugin(focus);
Alpine.plugin(collapse);

const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function api(url, options = {}) {
    const res = await fetch(url, {
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        ...options,
        body: options.body ? JSON.stringify(options.body) : undefined,
    });
    if (!res.ok) {
        const err = new Error(`Request failed: ${res.status}`);
        try { err.payload = await res.json(); } catch { /* no body */ }
        throw err;
    }
    return res.json();
}

/* ---------------------------------------------------------------------------
   UI store — drawers, overlays and quick view
--------------------------------------------------------------------------- */
Alpine.store('ui', {
    cartOpen: false,
    searchOpen: false,
    menuOpen: false,
    quickView: null,
    get anyOpen() {
        return this.cartOpen || this.searchOpen || this.menuOpen || !!this.quickView;
    },
    openCart() { this.closeAll(); this.cartOpen = true; },
    openSearch() { this.closeAll(); this.searchOpen = true; },
    openMenu() { this.closeAll(); this.menuOpen = true; },
    closeAll() { this.cartOpen = false; this.searchOpen = false; this.menuOpen = false; this.quickView = null; },
    async showQuickView(slug) {
        this.closeAll();
        const data = await api(`/api/products/${slug}`);
        this.quickView = data;
    },
});

/* ---------------------------------------------------------------------------
   Cart store — server session cart, mirrored client-side
--------------------------------------------------------------------------- */
Alpine.store('cart', {
    items: [],
    count: 0,
    subtotal: 0,
    subtotal_formatted: '₹0',
    free_shipping_threshold: 0,
    remaining: 0,
    remaining_formatted: '₹0',
    progress: 0,
    coupon: null,
    discount: 0,
    discount_formatted: '₹0',
    shipping_method: { code: '', name: '' },
    shipping: 0,
    shipping_formatted: '',
    tax: 0,
    tax_label: 'GST',
    tax_formatted: 'Included',
    total: 0,
    total_formatted: '₹0',
    recommendations: [],
    loaded: false,
    busy: false,
    apply(data) {
        Object.assign(this, data);
        this.loaded = true;
    },
    async load() {
        this.apply(await api('/cart/items'));
    },
    async add(productId, size, color = null, qty = 1, open = true) {
        this.busy = true;
        try {
            this.apply(await api('/cart/items', { method: 'POST', body: { product_id: productId, size, color, qty } }));
            if (open) Alpine.store('ui').openCart();
        } finally {
            this.busy = false;
        }
    },
    async update(key, qty) {
        this.apply(await api(`/cart/items/${key}`, { method: 'PATCH', body: { qty } }));
    },
    async remove(key) {
        this.apply(await api(`/cart/items/${key}`, { method: 'DELETE' }));
    },
});

/* ---------------------------------------------------------------------------
   Wishlist + recent searches — per-device, persisted in localStorage
--------------------------------------------------------------------------- */
Alpine.store('wishlist', {
    ids: Alpine.$persist([]).as('elan-wishlist'),
    has(id) { return this.ids.includes(id); },
    toggle(id) { this.ids = this.has(id) ? this.ids.filter((x) => x !== id) : [id, ...this.ids]; },
    get count() { return this.ids.length; },
});

Alpine.store('recent', {
    terms: Alpine.$persist([]).as('elan-recent-searches'),
    add(term) { this.terms = [term, ...this.terms.filter((t) => t !== term)].slice(0, 5); },
    clear() { this.terms = []; },
});

/* ---------------------------------------------------------------------------
   Components
--------------------------------------------------------------------------- */
Alpine.data('header', (transparent = false, transparentMobile = transparent) => ({
    transparent,
    transparentMobile,
    scrolled: false,
    hovered: false,
    mega: null,
    closeTimer: null,
    init() {
        const onScroll = () => { this.scrolled = window.scrollY > 24; };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    },
    get solid() {
        const transparent = window.innerWidth >= 1024 ? this.transparent : this.transparentMobile;
        return !transparent || this.scrolled || this.hovered || this.mega !== null || Alpine.store('ui').anyOpen;
    },
    openMega(key) { clearTimeout(this.closeTimer); this.mega = key; },
    scheduleClose() { clearTimeout(this.closeTimer); this.closeTimer = setTimeout(() => { this.mega = null; }, 120); },
}));

Alpine.data('productCard', (product) => ({
    product,
    sizesOpen: false,
    added: false,
    get singleSize() { return this.product.sizes.length === 1; },
    quickAdd() {
        if (this.singleSize) return this.add(this.product.sizes[0]);
        this.sizesOpen = true;
    },
    async add(size) {
        this.sizesOpen = false;
        this.added = true;
        await Alpine.store('cart').add(this.product.id, size, this.product.colors[0]?.name ?? null, 1, false);
        setTimeout(() => { this.added = false; Alpine.store('ui').openCart(); }, 450);
    },
}));

Alpine.data('quickView', () => ({
    size: null, color: null, qty: 1, image: 0,
    init() {
        this.$watch('$store.ui.quickView', (p) => {
            if (!p) return;
            this.size = p.sizes.length === 1 ? p.sizes[0] : null;
            this.color = p.colors[0]?.name ?? null;
            this.qty = 1;
            this.image = 0;
        });
    },
    async add() {
        const p = this.$store.ui.quickView;
        if (!p || !this.size) return;
        await this.$store.cart.add(p.id, this.size, this.color, this.qty, false);
        this.$store.ui.quickView = null;
        setTimeout(() => this.$store.ui.openCart(), 200);
    },
}));

Alpine.data('search', () => ({
    q: '',
    results: { products: [], categories: [] },
    loading: false,
    timer: null,
    init() {
        this.$watch('$store.ui.searchOpen', (open) => {
            if (open) { this.q = ''; this.results = { products: [], categories: [] }; this.$nextTick(() => this.$refs.input?.focus()); }
        });
        this.$watch('q', () => this.lookup());
    },
    lookup() {
        clearTimeout(this.timer);
        const term = this.q.trim();
        if (!term) { this.results = { products: [], categories: [] }; return; }
        this.timer = setTimeout(async () => {
            this.loading = true;
            try { this.results = await api(`/api/search?q=${encodeURIComponent(term)}`); } finally { this.loading = false; }
        }, 180);
    },
    submit(term = this.q) {
        term = term.trim();
        if (!term) return;
        this.$store.recent.add(term);
        window.location.href = `/search?q=${encodeURIComponent(term)}`;
    },
}));

Alpine.data('carousel', () => ({
    progress: 0, canPrev: false, canNext: true,
    init() {
        const el = this.$refs.track;
        const update = () => {
            const max = el.scrollWidth - el.clientWidth;
            this.progress = max > 0 ? el.scrollLeft / max : 0;
            this.canPrev = el.scrollLeft > 4;
            this.canNext = el.scrollLeft < max - 4;
        };
        update();
        el.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
    },
    scroll(dir) {
        const el = this.$refs.track;
        const card = el.querySelector('[data-slide]');
        const step = card ? card.offsetWidth + 24 : el.clientWidth * 0.8;
        el.scrollBy({ left: dir * step * 2, behavior: 'smooth' });
    },
}));

Alpine.data('newsletter', (source = 'homepage') => ({
    email: '', done: false, error: null, busy: false,
    async submit() {
        if (!this.email.includes('@')) return;
        this.busy = true; this.error = null;
        try {
            await api('/newsletter', { method: 'POST', body: { email: this.email, source } });
            this.done = true;
        } catch (e) {
            this.error = 'Something went wrong. Please try again.';
        } finally { this.busy = false; }
    },
}));

Alpine.data('parallax', (amount = 0.08) => ({
    y: 0,
    init() {
        const el = this.$el;
        const update = () => {
            const r = el.getBoundingClientRect();
            const vh = window.innerHeight;
            const p = (r.top + r.height / 2 - vh / 2) / (vh + r.height); // -0.5 … 0.5
            this.y = -p * amount * 100 * 2;
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
    },
}));

registerExtras(Alpine, api);
registerAppShell(Alpine);

// Lock body scroll while any overlay is open
Alpine.effect(() => {
    document.body.style.overflow = Alpine.store('ui').anyOpen ? 'hidden' : '';
});

window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') Alpine.store('ui').closeAll();
});

window.Alpine = Alpine;
Alpine.start();
Alpine.store('cart').load();
