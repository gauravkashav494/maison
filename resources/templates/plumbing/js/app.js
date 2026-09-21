/**
 * Plumbing template — storefront behaviour.
 * Talks to the same JSON endpoints as every template (/cart/items, /api/search, …);
 * only the UI layer is template-specific.
 */
import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import persist from '@alpinejs/persist';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';
import registerAppShell from '../../../js/app-shell';

Alpine.plugin(intersect);
Alpine.plugin(persist);
Alpine.plugin(focus);
Alpine.plugin(collapse);

const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function api(url, options = {}) {
    const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
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

const inr = (n) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(n));

/* ---------------------------------------------------------------------------
   Stores
--------------------------------------------------------------------------- */
Alpine.store('ui', {
    cartOpen: false,
    menuOpen: false,
    searchOpen: false,
    sizePicker: null, // product whose size must be chosen before adding
    toast: null,
    toastTimer: null,
    get anyOpen() { return this.cartOpen || this.menuOpen || this.searchOpen || !!this.sizePicker; },
    closeAll() { this.cartOpen = this.menuOpen = this.searchOpen = false; this.sizePicker = null; },
    openCart() { this.closeAll(); this.cartOpen = true; },
    openMenu() { this.closeAll(); this.menuOpen = true; },
    openCategories() { this.openMenu(); },
    openSearch() { this.closeAll(); this.searchOpen = true; },
    pickSize(product) { this.sizePicker = product; },
    notify(message, tone = 'ok') {
        clearTimeout(this.toastTimer);
        this.toast = { message, tone };
        this.toastTimer = setTimeout(() => { this.toast = null; }, 2400);
    },
});

Alpine.store('cart', {
    items: [], count: 0, subtotal: 0, subtotal_formatted: '₹0',
    free_shipping_threshold: 0, remaining: 0, remaining_formatted: '₹0', progress: 0,
    coupon: null, discount: 0, discount_formatted: '₹0',
    shipping_method: { code: '', name: '' }, shipping: 0, shipping_formatted: '',
    tax: 0, tax_label: 'GST', tax_formatted: 'Included',
    total: 0, total_formatted: '₹0', recommendations: [],
    loaded: false, busy: false, couponError: null, couponBusy: false,
    apply(data) { Object.assign(this, data); this.loaded = true; },
    async load() { this.apply(await api('/cart/items')); },
    get savings() {
        return this.items.reduce((s, i) => s + (i.product.compare_at_price ? (i.product.compare_at_price - i.product.price) * i.qty : 0), 0) + this.discount;
    },
    get savings_formatted() { return inr(this.savings); },
    line(productId, size = null) { return this.items.find((i) => i.product.id === productId && (size === null || i.size === size)); },
    qtyOf(productId, size = null) { return this.items.filter((i) => i.product.id === productId && (size === null || i.size === size)).reduce((s, i) => s + i.qty, 0); },
    async add(productId, size, color = null, qty = 1, open = false) {
        this.busy = true;
        try {
            this.apply(await api('/cart/items', { method: 'POST', body: { product_id: productId, size, color, qty } }));
            if (open) Alpine.store('ui').openCart(); else Alpine.store('ui').notify('Added to cart');
        } catch (e) {
            Alpine.store('ui').notify(e.payload?.message || 'Could not add this item', 'error');
        } finally { this.busy = false; }
    },
    async update(key, qty) { this.apply(await api(`/cart/items/${key}`, { method: 'PATCH', body: { qty } })); },
    async remove(key) { this.apply(await api(`/cart/items/${key}`, { method: 'DELETE' })); },
    async inc(productId, size, max = null) {
        const line = this.line(productId, size);
        if (line) {
            if (max && line.qty >= max) { Alpine.store('ui').notify(`Maximum ${max} per order`, 'error'); return; }
            await this.update(line.key, line.qty + 1);
        } else {
            await this.add(productId, size);
        }
    },
    async dec(productId, size) {
        const line = this.line(productId, size);
        if (!line) return;
        await this.update(line.key, line.qty - 1);
    },
    async applyCoupon(code) {
        this.couponError = null;
        if (!code?.trim()) return;
        this.couponBusy = true;
        try { this.apply(await api('/cart/coupon', { method: 'POST', body: { code } })); Alpine.store('ui').notify('Coupon applied'); }
        catch (e) { this.couponError = e.payload?.message || 'That code is not valid.'; }
        finally { this.couponBusy = false; }
    },
    async removeCoupon() { this.apply(await api('/cart/coupon', { method: 'DELETE' })); },
    async setShipping(code) { this.apply(await api('/cart/shipping', { method: 'POST', body: { code } })); },
});

Alpine.store('wishlist', {
    ids: Alpine.$persist([]).as('plumbing-wishlist'),
    has(id) { return this.ids.includes(id); },
    toggle(id) { this.ids = this.has(id) ? this.ids.filter((x) => x !== id) : [id, ...this.ids]; Alpine.store('ui').notify(this.has(id) ? 'Saved to wishlist' : 'Removed from wishlist'); },
    get count() { return this.ids.length; },
});

Alpine.store('recent', {
    terms: Alpine.$persist([]).as('plumbing-recent-searches'),
    add(term) { this.terms = [term, ...this.terms.filter((t) => t !== term)].slice(0, 6); },
    clear() { this.terms = []; },
});

Alpine.store('recent_products', {
    slugs: Alpine.$persist([]).as('plumbing-recently-viewed'),
    push(slug) { this.slugs = [slug, ...this.slugs.filter((s) => s !== slug)].slice(0, 10); },
});

Alpine.store('cookies', {
    prefs: Alpine.$persist(null).as('plumbing-cookie-consent'),
    get decided() { return this.prefs !== null; },
    acceptAll() { this.prefs = { essential: true, analytics: true, marketing: true, at: Date.now() }; },
    rejectAll() { this.prefs = { essential: true, analytics: false, marketing: false, at: Date.now() }; },
    save(analytics, marketing) { this.prefs = { essential: true, analytics: !!analytics, marketing: !!marketing, at: Date.now() }; },
});

/* ---------------------------------------------------------------------------
   Components
--------------------------------------------------------------------------- */
Alpine.data('header', () => ({
    scrolled: false,
    mega: null,
    closeTimer: null,
    init() {
        const onScroll = () => { this.scrolled = window.scrollY > 8; };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    },
    openMega(key) { clearTimeout(this.closeTimer); this.mega = key; },
    scheduleClose() { clearTimeout(this.closeTimer); this.closeTimer = setTimeout(() => { this.mega = null; }, 150); },
}));

Alpine.data('search', (suggestions = []) => ({
    q: '',
    results: { products: [], categories: [] },
    loading: false,
    focused: false,
    timer: null,
    suggestions,
    placeholderIndex: 0,
    init() {
        this.$watch('q', () => this.lookup());
        setInterval(() => { this.placeholderIndex = (this.placeholderIndex + 1) % Math.max(1, this.suggestions.length); }, 2800);
        this.$watch('$store.ui.searchOpen', (o) => { if (o) this.$nextTick(() => this.$refs.input?.focus()); });
    },
    get placeholder() { return this.suggestions.length ? `Try "${this.suggestions[this.placeholderIndex]}"` : 'Search products…'; },
    get showPanel() { return this.focused && (this.q.trim().length > 0 || this.suggestions.length > 0 || Alpine.store('recent').terms.length > 0); },
    lookup() {
        clearTimeout(this.timer);
        const term = this.q.trim();
        if (!term) { this.results = { products: [], categories: [] }; return; }
        this.timer = setTimeout(async () => {
            this.loading = true;
            try { this.results = await api(`/api/search?q=${encodeURIComponent(term)}`); } finally { this.loading = false; }
        }, 160);
    },
    submit(term = this.q) {
        term = term.trim();
        if (!term) return;
        Alpine.store('recent').add(term);
        window.location.href = `/search?q=${encodeURIComponent(term)}`;
    },
}));

/** Product card: Add → stepper synced to the server cart; multi-size products open the size sheet. */
Alpine.data('pCard', (product) => ({
    product,
    get size() { return this.product.sizes.length === 1 ? this.product.sizes[0] : null; },
    get multi() { return this.product.sizes.length > 1; },
    get qty() { return Alpine.store('cart').qtyOf(this.product.id, this.size); },
    add() {
        if (!this.product.in_stock) return;
        if (this.multi) { Alpine.store('ui').pickSize(this.product); return; }
        Alpine.store('cart').inc(this.product.id, this.size, this.product.max_qty);
    },
    inc() { if (this.multi) { Alpine.store('ui').pickSize(this.product); return; } Alpine.store('cart').inc(this.product.id, this.size, this.product.max_qty); },
    dec() { if (this.multi) { Alpine.store('ui').pickSize(this.product); return; } Alpine.store('cart').dec(this.product.id, this.size); },
}));

/** Bottom sheet for choosing a size/variant on multi-size products. */
Alpine.data('sizePicker', () => ({
    get p() { return Alpine.store('ui').sizePicker; },
    qtyOf(size) { return this.p ? Alpine.store('cart').qtyOf(this.p.id, size) : 0; },
    inc(size) { Alpine.store('cart').inc(this.p.id, size, this.p.max_qty); },
    dec(size) { Alpine.store('cart').dec(this.p.id, size); },
}));

Alpine.data('rail', () => ({
    canPrev: false, canNext: true,
    init() {
        const el = this.$refs.track;
        const update = () => { const max = el.scrollWidth - el.clientWidth; this.canPrev = el.scrollLeft > 4; this.canNext = el.scrollLeft < max - 4; };
        update();
        el.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        setTimeout(update, 300);
    },
    scroll(dir) {
        const el = this.$refs.track;
        const card = el.querySelector('[data-slide]');
        const step = card ? card.offsetWidth + 14 : el.clientWidth * 0.8;
        el.scrollBy({ left: dir * step * 3, behavior: 'smooth' });
    },
}));

Alpine.data('countdown', () => ({
    h: '00', m: '00', s: '00',
    init() {
        const tick = () => {
            const now = new Date();
            const end = new Date(now); end.setHours(23, 59, 59, 999);
            const diff = Math.max(0, end - now);
            this.h = String(Math.floor(diff / 3.6e6)).padStart(2, '0');
            this.m = String(Math.floor((diff % 3.6e6) / 6e4)).padStart(2, '0');
            this.s = String(Math.floor((diff % 6e4) / 1000)).padStart(2, '0');
        };
        tick();
        setInterval(tick, 1000);
    },
}));

Alpine.data('shopFilters', (initial) => ({
    open: false,
    sortOpen: false,
    f: { ...initial },
    toggle(list, value) {
        const arr = this.f[list] ?? [];
        this.f[list] = arr.includes(value) ? arr.filter((v) => v !== value) : [...arr, value];
    },
    has(list, value) { return (this.f[list] ?? []).includes(value); },
    setPrice(min, max) { this.f.min = min ?? ''; this.f.max = max ?? ''; },
    clear() {
        this.f = { category: [], subcategory: [], collection: [], brand: [], material: [], size: [], color: [], min: '', max: '', rating: '', availability: '', sale: false, discount: '', sort: this.f.sort, view: this.f.view };
        this.submit();
    },
    get activeCount() {
        let n = 0;
        for (const k of ['category', 'subcategory', 'collection', 'brand', 'material', 'size']) n += (this.f[k] ?? []).length;
        for (const k of ['rating', 'availability', 'discount']) if (this.f[k]) n++;
        if ((this.f.min ?? '') !== '' || (this.f.max ?? '') !== '') n++;
        if (this.f.sale) n++;
        return n;
    },
    submit() {
        const params = new URLSearchParams();
        for (const key of ['category', 'subcategory', 'collection', 'brand', 'material', 'size', 'color']) if (this.f[key]?.length) params.set(key, this.f[key].join(','));
        for (const key of ['min', 'max', 'rating', 'availability', 'discount', 'sort']) if (this.f[key] !== '' && this.f[key] != null && this.f[key] !== false) params.set(key, this.f[key]);
        if (this.f.sale) params.set('sale', '1');
        window.location.search = params.toString();
    },
}));

Alpine.data('productPage', (product) => ({
    p: product,
    image: 0,
    size: product.sizes.length === 1 ? product.sizes[0] : (product.sizes[0] ?? null),
    quantity: 1,
    lightbox: false,
    showSticky: false,
    tab: 'details',
    get qty() { return Alpine.store('cart').qtyOf(this.p.id, this.size); },
    get slides() {
        const s = product.images.map((src) => ({ type: 'image', src }));
        if (product.video_url) s.push({ type: 'video', src: product.video_url });
        return s;
    },
    init() {
        Alpine.store('recent_products').push(product.slug);
        const anchor = this.$refs.buy;
        if (anchor) new IntersectionObserver(([e]) => { this.showSticky = !e.isIntersecting && e.boundingClientRect.top < 0; }, { threshold: 0 }).observe(anchor);
        let x0 = null;
        this.$refs.stage?.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
        this.$refs.stage?.addEventListener('touchend', (e) => {
            if (x0 === null) return;
            const dx = e.changedTouches[0].clientX - x0; x0 = null;
            if (Math.abs(dx) > 40) this.image = (this.image + (dx < 0 ? 1 : -1) + this.slides.length) % this.slides.length;
        }, { passive: true });
    },
    next() { this.image = (this.image + 1) % this.slides.length; },
    prev() { this.image = (this.image - 1 + this.slides.length) % this.slides.length; },
    isYouTube(src) { return /youtube\.com|youtu\.be/.test(src); },
    embed(src) { const m = src.match(/(?:v=|youtu\.be\/|embed\/)([\w-]{6,})/); return m ? `https://www.youtube.com/embed/${m[1]}?rel=0` : src; },
    async add() {
        if (!this.p.in_stock) return;
        await Alpine.store('cart').add(this.p.id, this.size, null, this.quantity, false);
    },
    async buyNow() {
        if (!this.p.in_stock) return;
        await Alpine.store('cart').add(this.p.id, this.size, null, this.quantity, false);
        window.location.href = '/checkout';
    },
    share() {
        if (navigator.share) navigator.share({ title: this.p.name, url: window.location.href }).catch(() => {});
        else { navigator.clipboard?.writeText(window.location.href); Alpine.store('ui').notify('Link copied'); }
    },
}));

Alpine.data('productRail', (mode) => ({
    items: [], loading: true,
    async init() {
        const exclude = this.$el.dataset.exclude;
        const fetchList = async () => {
            const l = mode === 'wishlist' ? Alpine.store('wishlist').ids : Alpine.store('recent_products').slugs.filter((s) => s !== exclude);
            if (!l.length) { this.items = []; this.loading = false; return; }
            const q = mode === 'wishlist' ? `ids=${l.join(',')}` : `slugs=${l.join(',')}`;
            try { this.items = await api(`/api/products?${q}`); } finally { this.loading = false; }
        };
        await fetchList();
        if (mode === 'wishlist') this.$watch('$store.wishlist.ids', fetchList);
    },
}));

Alpine.data('reviewForm', () => ({ open: false, rating: 5, hover: 0 }));

Alpine.data('checkout', (init) => ({
    shipping: init.shipping,
    payment: init.payment,
    codFee: init.codFee,
    submitting: false,
    async setShipping(code) { this.shipping = code; await Alpine.store('cart').setShipping(code); },
    get codExtra() { return this.payment === 'cod' ? this.codFee : 0; },
    fmt: inr,
}));

Alpine.data('newsletter', (source = 'plumbing') => ({
    email: '', done: false, error: null, busy: false,
    async submit() {
        if (!this.email.includes('@')) return;
        this.busy = true; this.error = null;
        try { await api('/newsletter', { method: 'POST', body: { email: this.email, source } }); this.done = true; }
        catch { this.error = 'Something went wrong. Please try again.'; }
        finally { this.busy = false; }
    },
}));

Alpine.data('accordion', (first = null) => ({ open: first, toggle(k) { this.open = this.open === k ? null : k; } }));

/* Body scroll lock + escape */
Alpine.effect(() => { document.body.style.overflow = Alpine.store('ui').anyOpen ? 'hidden' : ''; });
window.addEventListener('keydown', (e) => { if (e.key === 'Escape') Alpine.store('ui').closeAll(); });

window.Alpine = Alpine;
registerAppShell(Alpine);
Alpine.start();
Alpine.store('cart').load();
