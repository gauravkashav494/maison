/**
 * Heritage Grocery template — storefront behaviour.
 * Uses the shared JSON endpoints (/cart/items, /api/search, /api/products/{slug}, …);
 * everything here is presentation only.
 */
import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import persist from '@alpinejs/persist';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

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
    cartOpen: false, menuOpen: false, searchOpen: false, quickView: null, quickViewLoading: false,
    toast: null, toastTimer: null,
    get anyOpen() { return this.cartOpen || this.menuOpen || this.searchOpen || !!this.quickView || this.quickViewLoading; },
    closeAll() { this.cartOpen = this.menuOpen = this.searchOpen = false; this.quickView = null; this.quickViewLoading = false; },
    openCart() { this.closeAll(); this.cartOpen = true; },
    openMenu() { this.closeAll(); this.menuOpen = true; },
    openSearch() { this.closeAll(); this.searchOpen = true; },
    async showQuickView(slug) {
        this.closeAll();
        this.quickViewLoading = true;
        try { this.quickView = await api(`/api/products/${slug}`); } finally { this.quickViewLoading = false; }
    },
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
    get savings() { return this.items.reduce((s, i) => s + (i.product.compare_at_price ? (i.product.compare_at_price - i.product.price) * i.qty : 0), 0) + this.discount; },
    get savings_formatted() { return inr(this.savings); },
    line(productId, size = null) { return this.items.find((i) => i.product.id === productId && (size === null || i.size === size)); },
    qtyOf(productId, size = null) { return this.items.filter((i) => i.product.id === productId && (size === null || i.size === size)).reduce((s, i) => s + i.qty, 0); },
    async add(productId, size, color = null, qty = 1, open = false) {
        this.busy = true;
        try {
            this.apply(await api('/cart/items', { method: 'POST', body: { product_id: productId, size, color, qty } }));
            if (open) Alpine.store('ui').openCart(); else Alpine.store('ui').notify('Added to your cart');
        } catch (e) {
            Alpine.store('ui').notify(e.payload?.message || 'Could not add this item', 'error');
        } finally { this.busy = false; }
    },
    async update(key, qty) { this.apply(await api(`/cart/items/${key}`, { method: 'PATCH', body: { qty } })); },
    async remove(key) { this.apply(await api(`/cart/items/${key}`, { method: 'DELETE' })); },
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

/** Save-for-later: lines moved out of the server cart and kept per device. */
Alpine.store('saved', {
    items: Alpine.$persist([]).as('heritage-saved-for-later'),
    async save(line) {
        this.items = [{ id: line.product.id, slug: line.product.slug, name: line.product.name, image: line.product.images[0], size: line.size, price_formatted: line.product.price_formatted, url: line.product.url }, ...this.items.filter((s) => !(s.id === line.product.id && s.size === line.size))];
        await Alpine.store('cart').remove(line.key);
        Alpine.store('ui').notify('Saved for later');
    },
    async moveToCart(item) {
        await Alpine.store('cart').add(item.id, item.size, null, 1, false);
        this.remove(item);
    },
    remove(item) { this.items = this.items.filter((s) => !(s.id === item.id && s.size === item.size)); },
});

Alpine.store('wishlist', {
    ids: Alpine.$persist([]).as('heritage-wishlist'),
    has(id) { return this.ids.includes(id); },
    toggle(id) { this.ids = this.has(id) ? this.ids.filter((x) => x !== id) : [id, ...this.ids]; Alpine.store('ui').notify(this.has(id) ? 'Added to your wishlist' : 'Removed from wishlist'); },
    get count() { return this.ids.length; },
});

Alpine.store('recent', {
    terms: Alpine.$persist([]).as('heritage-recent-searches'),
    add(term) { this.terms = [term, ...this.terms.filter((t) => t !== term)].slice(0, 6); },
    clear() { this.terms = []; },
});

Alpine.store('recent_products', {
    slugs: Alpine.$persist([]).as('heritage-recently-viewed'),
    push(slug) { this.slugs = [slug, ...this.slugs.filter((s) => s !== slug)].slice(0, 10); },
});

Alpine.store('cookies', {
    prefs: Alpine.$persist(null).as('heritage-cookie-consent'),
    get decided() { return this.prefs !== null; },
    acceptAll() { this.prefs = { essential: true, analytics: true, marketing: true, at: Date.now() }; },
    rejectAll() { this.prefs = { essential: true, analytics: false, marketing: false, at: Date.now() }; },
    save(analytics, marketing) { this.prefs = { essential: true, analytics: !!analytics, marketing: !!marketing, at: Date.now() }; },
});

/* ---------------------------------------------------------------------------
   Components
--------------------------------------------------------------------------- */
Alpine.data('header', () => ({
    scrolled: false, mega: null, closeTimer: null,
    init() {
        const onScroll = () => { this.scrolled = window.scrollY > 40; };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    },
    openMega(key) { clearTimeout(this.closeTimer); this.mega = key; },
    scheduleClose() { clearTimeout(this.closeTimer); this.closeTimer = setTimeout(() => { this.mega = null; }, 160); },
}));

Alpine.data('promoBar', (count) => ({
    index: 0, count,
    init() { if (this.count > 1) setInterval(() => { this.index = (this.index + 1) % this.count; }, 4200); },
}));

Alpine.data('search', (suggestions = []) => ({
    q: '', results: { products: [], categories: [] }, loading: false, open: false, timer: null, suggestions,
    init() {
        this.$watch('q', () => this.lookup());
        this.$watch('$store.ui.searchOpen', (o) => { if (o) this.$nextTick(() => this.$refs.input?.focus()); });
    },
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

/** Product card: quantity selector + Add to cart (multi-pack products open quick view to pick a size). */
Alpine.data('hCard', (product) => ({
    product, qty: 1, added: false,
    size: product.sizes[0] ?? null,
    get multi() { return this.product.sizes.length > 1; },
    get max() { return this.product.max_qty || 20; },
    inc() { if (this.qty < this.max) this.qty++; },
    dec() { if (this.qty > 1) this.qty--; },
    async add() {
        if (!this.product.in_stock) return;
        if (!this.size) { Alpine.store('ui').showQuickView(this.product.slug); return; }
        await Alpine.store('cart').add(this.product.id, this.size, null, this.qty, false);
        this.added = true; setTimeout(() => { this.added = false; }, 1400);
        this.qty = 1;
    },
}));

Alpine.data('quickView', () => ({
    size: null, qty: 1, image: 0,
    init() {
        this.$watch('$store.ui.quickView', (p) => { if (!p) return; this.size = p.sizes[0] ?? null; this.qty = 1; this.image = 0; });
    },
    get p() { return Alpine.store('ui').quickView; },
    async add(buyNow = false) {
        if (!this.p || !this.size) return;
        await Alpine.store('cart').add(this.p.id, this.size, null, this.qty, false);
        if (buyNow) { window.location.href = '/checkout'; return; }
        Alpine.store('ui').quickView = null;
        setTimeout(() => Alpine.store('ui').openCart(), 150);
    },
}));

Alpine.data('heroCarousel', (count, interval = 6500) => ({
    index: 0, count, timer: null, paused: false,
    init() { this.play(); },
    play() { clearInterval(this.timer); if (this.count > 1) this.timer = setInterval(() => { if (!this.paused) this.next(); }, interval); },
    next() { this.index = (this.index + 1) % this.count; },
    prev() { this.index = (this.index - 1 + this.count) % this.count; },
    go(i) { this.index = i; this.play(); },
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
        const step = card ? card.offsetWidth + 16 : el.clientWidth * 0.8;
        el.scrollBy({ left: dir * step * 2, behavior: 'smooth' });
    },
}));

Alpine.data('testimonials', (count) => ({
    index: 0, count,
    next() { this.index = (this.index + 1) % this.count; },
    prev() { this.index = (this.index - 1 + this.count) % this.count; },
}));

Alpine.data('shopFilters', (initial) => ({
    open: false, panel: null,
    f: { ...initial },
    toggle(list, value) { const arr = this.f[list] ?? []; this.f[list] = arr.includes(value) ? arr.filter((v) => v !== value) : [...arr, value]; },
    has(list, value) { return (this.f[list] ?? []).includes(value); },
    setPrice(min, max) { this.f.min = min ?? ''; this.f.max = max ?? ''; },
    clear() {
        this.f = { category: [], subcategory: [], collection: [], brand: [], material: [], size: [], color: [], diet: [], min: '', max: '', rating: '', availability: '', discount: '', sale: false, veg: false, sort: this.f.sort, view: this.f.view };
        this.submit();
    },
    get activeCount() {
        let n = 0;
        for (const k of ['category', 'subcategory', 'collection', 'brand', 'size', 'diet']) n += (this.f[k] ?? []).length;
        for (const k of ['rating', 'availability', 'discount']) if (this.f[k]) n++;
        if ((this.f.min ?? '') !== '' || (this.f.max ?? '') !== '') n++;
        if (this.f.sale) n++;
        if (this.f.veg) n++;
        return n;
    },
    submit() {
        const params = new URLSearchParams();
        for (const key of ['category', 'subcategory', 'collection', 'brand', 'material', 'size', 'color', 'diet']) if (this.f[key]?.length) params.set(key, this.f[key].join(','));
        for (const key of ['min', 'max', 'rating', 'availability', 'discount', 'sort']) if (this.f[key] !== '' && this.f[key] != null && this.f[key] !== false) params.set(key, this.f[key]);
        if (this.f.sale) params.set('sale', '1');
        if (this.f.veg) params.set('veg', '1');
        window.location.search = params.toString();
    },
}));

Alpine.data('productPage', (product) => ({
    p: product, image: 0, qty: 1, lightbox: false, showSticky: false, tab: 'description', zoom: { on: false, x: 50, y: 50 },
    size: product.sizes[0] ?? null,
    get slides() {
        const s = product.images.map((src) => ({ type: 'image', src }));
        if (product.video_url) s.push({ type: 'video', src: product.video_url });
        return s;
    },
    get inCart() { return Alpine.store('cart').qtyOf(this.p.id, this.size); },
    get max() { return this.p.max_qty || 20; },
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
    move(e) { const r = e.currentTarget.getBoundingClientRect(); this.zoom.x = ((e.clientX - r.left) / r.width) * 100; this.zoom.y = ((e.clientY - r.top) / r.height) * 100; },
    next() { this.image = (this.image + 1) % this.slides.length; },
    prev() { this.image = (this.image - 1 + this.slides.length) % this.slides.length; },
    isYouTube(src) { return /youtube\.com|youtu\.be/.test(src); },
    embed(src) { const m = src.match(/(?:v=|youtu\.be\/|embed\/)([\w-]{6,})/); return m ? `https://www.youtube.com/embed/${m[1]}?rel=0` : src; },
    async add(buyNow = false) {
        if (!this.size) return;
        await Alpine.store('cart').add(this.p.id, this.size, null, this.qty, !buyNow);
        if (buyNow) window.location.href = '/checkout';
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
    shipping: init.shipping, payment: init.payment, codFee: init.codFee, submitting: false,
    async setShipping(code) { this.shipping = code; await Alpine.store('cart').setShipping(code); },
    get codExtra() { return this.payment === 'cod' ? this.codFee : 0; },
    fmt: inr,
}));

Alpine.data('newsletter', (source = 'heritage') => ({
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

Alpine.data('reveal', () => ({ shown: false }));

Alpine.effect(() => { document.body.style.overflow = Alpine.store('ui').anyOpen ? 'hidden' : ''; });
window.addEventListener('keydown', (e) => { if (e.key === 'Escape') Alpine.store('ui').closeAll(); });

window.Alpine = Alpine;
Alpine.start();
Alpine.store('cart').load();
