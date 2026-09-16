/**
 * Storefront components added with the full page set: shop filters, product gallery,
 * reviews, recently viewed, coupons/shipping on the cart, checkout, cookie consent.
 * Imported by app.js after the core stores are defined.
 */
export default function registerExtras(Alpine, api) {
    /* ---- Cart extras: promo codes + shipping method ---------------------- */
    const cart = Alpine.store('cart');
    Object.assign(cart, {
        couponError: null,
        couponBusy: false,
        async applyCoupon(code) {
            this.couponError = null;
            if (!code?.trim()) return;
            this.couponBusy = true;
            try {
                this.apply(await api('/cart/coupon', { method: 'POST', body: { code } }));
            } catch (e) {
                this.couponError = e.payload?.message || 'That code is not valid.';
            } finally {
                this.couponBusy = false;
            }
        },
        async removeCoupon() {
            this.apply(await api('/cart/coupon', { method: 'DELETE' }));
        },
        async setShipping(code) {
            this.apply(await api('/cart/shipping', { method: 'POST', body: { code } }));
        },
    });

    /* ---- Recently viewed (per device) ------------------------------------- */
    Alpine.store('recent_products', {
        slugs: Alpine.$persist([]).as('elan-recently-viewed'),
        push(slug) { this.slugs = [slug, ...this.slugs.filter((s) => s !== slug)].slice(0, 8); },
    });

    /* ---- Cookie consent --------------------------------------------------- */
    Alpine.store('cookies', {
        prefs: Alpine.$persist(null).as('elan-cookie-consent'), // null = not decided
        get decided() { return this.prefs !== null; },
        acceptAll() { this.prefs = { essential: true, analytics: true, marketing: true, at: Date.now() }; },
        rejectAll() { this.prefs = { essential: true, analytics: false, marketing: false, at: Date.now() }; },
        save(analytics, marketing) { this.prefs = { essential: true, analytics: !!analytics, marketing: !!marketing, at: Date.now() }; },
    });

    /* ---- Shop filters ------------------------------------------------------ */
    Alpine.data('shopFilters', (initial) => ({
        open: false,          // mobile bottom sheet
        panel: null,          // which accordion section is expanded on mobile
        f: { ...initial },
        toggle(list, value) {
            const arr = this.f[list] ?? [];
            this.f[list] = arr.includes(value) ? arr.filter((v) => v !== value) : [...arr, value];
        },
        has(list, value) { return (this.f[list] ?? []).includes(value); },
        clear() {
            this.f = { category: [], subcategory: [], collection: [], brand: [], material: [], size: [], color: [], min: '', max: '', rating: '', availability: '', sale: false, sort: this.f.sort, view: this.f.view };
            this.submit();
        },
        submit() {
            const params = new URLSearchParams();
            for (const key of ['category', 'subcategory', 'collection', 'brand', 'material', 'size', 'color']) {
                if (this.f[key]?.length) params.set(key, this.f[key].join(','));
            }
            for (const key of ['min', 'max', 'rating', 'availability', 'sort', 'view']) {
                if (this.f[key] !== '' && this.f[key] != null && this.f[key] !== false) params.set(key, this.f[key]);
            }
            if (this.f.sale) params.set('sale', '1');
            window.location.search = params.toString();
        },
    }));

    /* ---- Product page: gallery, zoom, variant state ----------------------- */
    Alpine.data('productPage', (product, opts = {}) => ({
        p: product,
        image: 0,
        size: product.sizes.length === 1 ? product.sizes[0] : null,
        color: product.colors[0]?.name ?? null,
        qty: 1,
        open: 'description',
        lightbox: false,
        zoom: { on: false, x: 50, y: 50 },
        showSticky: false,
        get slides() {
            const s = product.images.map((src) => ({ type: 'image', src }));
            if (product.video_url) s.push({ type: 'video', src: product.video_url });
            return s;
        },
        get current() { return this.slides[this.image]; },
        init() {
            Alpine.store('recent_products').push(product.slug);
            const anchor = this.$refs.buy;
            if (anchor) {
                new IntersectionObserver(([e]) => { this.showSticky = !e.isIntersecting && e.boundingClientRect.top < 0; }, { threshold: 0 }).observe(anchor);
            }
            // Swipe on the main image (mobile)
            let x0 = null;
            this.$refs.stage?.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
            this.$refs.stage?.addEventListener('touchend', (e) => {
                if (x0 === null) return;
                const dx = e.changedTouches[0].clientX - x0; x0 = null;
                if (Math.abs(dx) > 40) this.image = (this.image + (dx < 0 ? 1 : -1) + this.slides.length) % this.slides.length;
            }, { passive: true });
        },
        move(e) {
            const r = e.currentTarget.getBoundingClientRect();
            this.zoom.x = ((e.clientX - r.left) / r.width) * 100;
            this.zoom.y = ((e.clientY - r.top) / r.height) * 100;
        },
        next() { this.image = (this.image + 1) % this.slides.length; },
        prev() { this.image = (this.image - 1 + this.slides.length) % this.slides.length; },
        isYouTube(src) { return /youtube\.com|youtu\.be/.test(src); },
        embed(src) {
            const m = src.match(/(?:v=|youtu\.be\/|embed\/)([\w-]{6,})/);
            return m ? `https://www.youtube.com/embed/${m[1]}?rel=0` : src;
        },
        async add(buyNow = false) {
            if (!this.size) { this.$refs.sizes?.scrollIntoView({ block: 'center', behavior: 'smooth' }); this.sizeError = true; return; }
            await Alpine.store('cart').add(this.p.id, this.size, this.color, this.qty, !buyNow);
            if (buyNow) window.location.href = '/checkout';
        },
        sizeError: false,
    }));

    /* ---- Recently viewed / wishlist product rails (client-side lists) ----- */
    Alpine.data('productRail', (mode) => ({
        items: [],
        loading: true,
        async init() {
            const list = mode === 'wishlist' ? Alpine.store('wishlist').ids : Alpine.store('recent_products').slugs;
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

    /* ---- Reviews form ------------------------------------------------------ */
    Alpine.data('reviewForm', () => ({ open: false, rating: 5, hover: 0 }));

    /* ---- Checkout ---------------------------------------------------------- */
    Alpine.data('checkout', (init) => ({
        shipping: init.shipping,
        payment: init.payment,
        codFee: init.codFee,
        sameAsBilling: true,
        submitting: false,
        async setShipping(code) { this.shipping = code; await Alpine.store('cart').setShipping(code); },
        get codExtra() { return this.payment === 'cod' ? this.codFee : 0; },
        fmt(n) { return '₹' + new Intl.NumberFormat('en-IN').format(Math.round(n)); },
    }));

    /* ---- FAQ accordion / generic accordion -------------------------------- */
    Alpine.data('accordion', (first = null) => ({ open: first, toggle(k) { this.open = this.open === k ? null : k; } }));
}
