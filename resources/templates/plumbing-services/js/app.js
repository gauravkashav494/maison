/**
 * Plumbing Services template — site behaviour.
 * Lead-generation UI only: no cart. Service search, location picker, contact
 * sheet, hero carousel, the step-by-step booking wizard and small helpers.
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

async function api(url) {
    const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
    if (!res.ok) throw new Error(`Request failed: ${res.status}`);
    return res.json();
}

/* ---------------------------------------------------------------------------
   Stores
--------------------------------------------------------------------------- */
Alpine.store('ui', {
    menuOpen: false,
    searchOpen: false,
    contactOpen: false,
    locationOpen: false,
    toast: null,
    toastTimer: null,
    get anyOpen() { return this.menuOpen || this.searchOpen || this.contactOpen || this.locationOpen; },
    closeAll() { this.menuOpen = this.searchOpen = this.contactOpen = this.locationOpen = false; },
    openMenu() { this.closeAll(); this.menuOpen = true; },
    openSearch() { this.closeAll(); this.searchOpen = true; },
    openContact() { this.closeAll(); this.contactOpen = true; },
    openLocation() { this.closeAll(); this.locationOpen = true; },
    notify(message, tone = 'ok') {
        clearTimeout(this.toastTimer);
        this.toast = { message, tone };
        this.toastTimer = setTimeout(() => { this.toast = null; }, 2400);
    },
});

/* The visitor's chosen service area (device-level, no account needed). */
Alpine.store('area', {
    current: Alpine.$persist(null).as('ps-area'),
    get name() { return this.current?.name || null; },
    set(area) { this.current = area ? { name: area.name, slug: area.slug, response: area.response_time || null } : null; Alpine.store('ui').locationOpen = false; if (area) Alpine.store('ui').notify(`Showing services for ${area.name}`); },
});

Alpine.store('recent', {
    terms: Alpine.$persist([]).as('ps-recent-searches'),
    add(t) { t = t.trim(); if (!t) return; this.terms = [t, ...this.terms.filter((x) => x.toLowerCase() !== t.toLowerCase())].slice(0, 6); },
    clear() { this.terms = []; },
});

Alpine.store('cookies', {
    prefs: Alpine.$persist(null).as('ps-cookie-consent'),
    get decided() { return this.prefs !== null; },
    acceptAll() { this.prefs = { essential: true, analytics: true, marketing: true, at: Date.now() }; },
    rejectAll() { this.prefs = { essential: true, analytics: false, marketing: false, at: Date.now() }; },
    save(analytics, marketing) { this.prefs = { essential: true, analytics: !!analytics, marketing: !!marketing, at: Date.now() }; },
});

/* ---------------------------------------------------------------------------
   Components
--------------------------------------------------------------------------- */

/* Desktop header: shadow on scroll, mega menu hover intent. */
Alpine.data('header', () => ({
    scrolled: false,
    mega: false,
    megaTimer: null,
    init() { this.onScroll(); window.addEventListener('scroll', () => this.onScroll(), { passive: true }); },
    onScroll() { this.scrolled = window.scrollY > 8; },
    openMega() { clearTimeout(this.megaTimer); this.mega = true; },
    closeMega() { this.megaTimer = setTimeout(() => { this.mega = false; }, 120); },
}));

/* Service search: rotating placeholder, live suggestions from /api/services. */
Alpine.data('search', (examples = [], base = 'Search plumbing services') => ({
    q: '',
    focused: false,
    loading: false,
    results: { services: [], problems: [] },
    examples,
    exampleIndex: 0,
    timer: null,
    rotateTimer: null,
    get placeholder() { return this.examples.length ? `Search "${this.examples[this.exampleIndex]}"` : base; },
    get showPanel() { return this.focused; },
    get empty() { return this.q.trim().length > 0 && !this.loading && !this.results.services.length && !this.results.problems.length; },
    init() {
        if (this.examples.length > 1) this.rotateTimer = setInterval(() => { if (!this.q) this.exampleIndex = (this.exampleIndex + 1) % this.examples.length; }, 2800);
        this.$watch('q', () => this.lookup());
    },
    async lookup() {
        clearTimeout(this.timer);
        const q = this.q.trim();
        this.timer = setTimeout(async () => {
            this.loading = true;
            try { this.results = await api('/api/services?q=' + encodeURIComponent(q)); } catch { this.results = { services: [], problems: [] }; }
            this.loading = false;
        }, q ? 180 : 0);
    },
    open() { this.focused = true; if (!this.results.services.length) this.lookup(); },
    close() { this.focused = false; },
    submit() { const q = this.q.trim(); if (!q) return; Alpine.store('recent').add(q); window.location.assign('/services?q=' + encodeURIComponent(q)); },
    pick(term) { this.q = term; this.submit(); },
}));

/* Horizontal rail with arrow buttons on desktop. */
Alpine.data('rail', () => ({
    atStart: true, atEnd: false,
    init() { this.$nextTick(() => this.update()); this.$refs.track?.addEventListener('scroll', () => this.update(), { passive: true }); window.addEventListener('resize', () => this.update()); },
    update() { const t = this.$refs.track; if (!t) return; this.atStart = t.scrollLeft <= 4; this.atEnd = t.scrollLeft + t.clientWidth >= t.scrollWidth - 4; },
    go(dir) { const t = this.$refs.track; if (!t) return; t.scrollBy({ left: dir * Math.max(240, t.clientWidth * 0.8), behavior: 'smooth' }); },
}));

/* Hero slides: auto-rotate, swipe, pause on hover. */
Alpine.data('heroCarousel', (count = 1, interval = 6000) => ({
    index: 0, count, timer: null, startX: null,
    init() { if (this.count > 1) this.play(); },
    play() { clearInterval(this.timer); if (this.count > 1) this.timer = setInterval(() => this.next(), interval); },
    pause() { clearInterval(this.timer); },
    go(i) { this.index = (i + this.count) % this.count; this.play(); },
    next() { this.go(this.index + 1); },
    prev() { this.go(this.index - 1); },
    touchStart(e) { this.startX = e.touches[0].clientX; this.pause(); },
    touchEnd(e) { if (this.startX === null) return; const dx = e.changedTouches[0].clientX - this.startX; if (Math.abs(dx) > 40) (dx < 0 ? this.next() : this.prev()); else this.play(); this.startX = null; },
}));

/* Before/after image comparison (range input drives a CSS variable). */
Alpine.data('beforeAfter', () => ({ pos: 50 }));

/* Booking wizard: six short steps with validation per step, summary at the end. */
Alpine.data('booking', (cfg) => ({
    step: 1,
    total: 6,
    services: cfg.services || [],
    areas: cfg.areas || [],
    slots: cfg.slots || {},
    errors: {},
    f: {
        service_id: cfg.service?.id || '',
        service_name: cfg.service?.name || '',
        other: false,
        problem: cfg.problem || '',
        address: '',
        area: cfg.area || '',
        service_area_id: '',
        when: cfg.emergency ? 'today' : '',
        preferred_date: '',
        time_slot: cfg.emergency ? 'asap' : '',
        name: cfg.user?.name || '',
        phone: cfg.user?.phone || '',
        email: cfg.user?.email || '',
        type: cfg.emergency ? 'emergency' : 'booking',
    },
    submitting: false,
    get today() { return new Date().toISOString().slice(0, 10); },
    get tomorrow() { const d = new Date(); d.setDate(d.getDate() + 1); return d.toISOString().slice(0, 10); },
    get selectedService() { return this.services.find((s) => String(s.id) === String(this.f.service_id)); },
    get serviceLabel() { return this.selectedService?.name || this.f.service_name || 'Not sure yet'; },
    get dateLabel() {
        const d = this.f.preferred_date; if (!d) return this.f.time_slot === 'asap' ? 'As soon as possible' : '—';
        if (d === this.today) return 'Today'; if (d === this.tomorrow) return 'Tomorrow';
        return new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' });
    },
    get slotLabel() { return this.slots[this.f.time_slot] || '—'; },
    get progress() { return Math.round((this.step / this.total) * 100); },
    init() {
        if (cfg.service) this.step = 2;
        if (cfg.emergency) { this.f.preferred_date = this.today; }
        const saved = Alpine.store('area').current; if (saved && !this.f.area) { this.f.area = saved.name; const m = this.areas.find((a) => a.slug === saved.slug); if (m) this.f.service_area_id = m.id; }
        this.$watch('f.area', (v) => { const m = this.areas.find((a) => a.name.toLowerCase() === String(v).toLowerCase()); this.f.service_area_id = m ? m.id : ''; });
        // Server-side validation errors land on the right step.
        if (cfg.errors && Object.keys(cfg.errors).length) { this.errors = cfg.errors; this.step = this.stepFor(Object.keys(cfg.errors)[0]); Object.assign(this.f, cfg.old || {}); }
    },
    stepFor(field) { return ({ service_id: 1, service_name: 1, problem: 2, address: 3, area: 3, preferred_date: 4, time_slot: 4, name: 5, phone: 5, email: 5 })[field] || 1; },
    pickService(s) { this.f.service_id = s ? s.id : ''; this.f.service_name = s ? s.name : ''; this.f.other = !s; this.f.type = s?.is_emergency ? 'emergency' : 'booking'; if (s) this.next(); },
    pickWhen(when) { this.f.when = when; if (when === 'today') this.f.preferred_date = this.today; else if (when === 'tomorrow') this.f.preferred_date = this.tomorrow; else if (when === 'asap') { this.f.preferred_date = this.today; this.f.time_slot = 'asap'; } else this.f.preferred_date = ''; },
    validate() {
        const e = {};
        if (this.step === 1 && !this.f.service_id && !this.f.service_name.trim()) e.service_name = 'Pick a service or tell us what you need.';
        if (this.step === 3 && !this.f.address.trim()) e.address = 'Enter the address where the plumber should come.';
        if (this.step === 4) { if (!this.f.preferred_date) e.preferred_date = 'Choose a day.'; if (!this.f.time_slot) e.time_slot = 'Choose a time.'; }
        if (this.step === 5) {
            if (!this.f.name.trim()) e.name = 'Enter your name.';
            if (!/^[0-9+\s-]{10,15}$/.test(this.f.phone.trim())) e.phone = 'Enter a valid 10-digit mobile number.';
            if (this.f.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(this.f.email)) e.email = 'That email does not look right.';
        }
        this.errors = e;
        return Object.keys(e).length === 0;
    },
    next() { if (!this.validate()) return; if (this.step < this.total) { this.step++; this.scrollTop(); } },
    back() { if (this.step > 1) { this.step--; this.errors = {}; this.scrollTop(); } },
    goto(n) { if (n < this.step) { this.step = n; this.errors = {}; this.scrollTop(); } },
    scrollTop() { this.$nextTick(() => { this.$root.scrollIntoView({ behavior: 'smooth', block: 'start' }); }); },
    submit() { if (!this.validate()) return; this.submitting = true; this.$refs.form.submit(); },
}));

/* Plain forms (quote, contact): client-side hint before the server validates. */
Alpine.data('leadForm', () => ({
    submitting: false,
    submit() { this.submitting = true; this.$el.submit(); },
}));

/* Reveal-on-scroll helper (subtle, respects reduced motion). */
Alpine.data('reveal', () => ({
    shown: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
}));

registerAppShell(Alpine);
window.Alpine = Alpine;
Alpine.start();
