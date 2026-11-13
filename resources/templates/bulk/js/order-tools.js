/**
 * Bulk Order template — the order sheet and the quick-order pad.
 *
 * Both end in the same place: a list of lines (product, options, quantity) posted one by one
 * to the shared cart endpoint, exactly as a single "Add" anywhere else would post them. The
 * lines themselves are built on the server (App\Templates\Bulk\OrderLines), so prices, codes,
 * stock and per-line limits here are the server's, never worked out in the browser.
 *
 * This module must be imported before the base bundle: that bundle starts Alpine as soon as
 * it runs, and a component registered after the start is never picked up.
 */
import Alpine from 'alpinejs';

const rupees = (n) => '₹' + Math.round(n || 0).toLocaleString('en-IN');

/** Adds lines to the cart one after another; returns which keys succeeded and why others failed. */
async function addLines(lines) {
    const cart = Alpine.store('cart');
    const added = [];
    const failed = {};
    for (const line of lines) {
        try {
            await cart.add(line.product_id, null, null, line.qty, false, line.options);
            added.push(line.key);
        } catch (e) {
            failed[line.key] = e.payload?.message || 'Could not be added. Please try again.';
        }
    }
    return { added, failed };
}

/** Quantity typed into a box, held to 0…max. */
function clampQty(value, max) {
    let n = parseInt(value, 10);
    if (isNaN(n) || n < 0) n = 0;
    return Math.min(n, max);
}

/* ---------------------------------------------------------------------------
   Phone fields: checked as the buyer types, with the server's own rules
--------------------------------------------------------------------------- */

/**
 * The message for a phone number, or '' when it is fine: exactly 10 digits, as on the server
 * (StorefrontRequest::phone). The shared phone-input script has already tidied the field —
 * digits only, a +91 or leading 0 dropped — by the time this reads it.
 */
export function phoneError(value, required = true) {
    const v = String(value || '').trim();
    if (!v) return required ? 'Please enter your phone number.' : '';
    if (!/^\d{10}$/.test(v)) return `Please enter a 10-digit phone number — ${v.replace(/\D/g, '').length} entered so far.`;
    return '';
}

Alpine.data('phoneField', (required = true, serverError = '') => ({
    error: serverError,
    touched: false,

    init() {
        // Checked before the form's own submit handler (capture phase), so an invalid number
        // never reaches the server and a checkout button never gets stuck on "Placing order…".
        this.$refs.input.form?.addEventListener('submit', (event) => {
            this.touched = true;
            this.check();
            if (this.error) {
                event.preventDefault();
                event.stopImmediatePropagation();
                this.$refs.input.focus();
            }
        }, true);
    },

    /** Called on each keystroke (after the shared script has tidied the field). */
    clean() {
        // A message from the server is about the old value: drop it once they start editing.
        if (this.touched) this.check(); else this.error = '';
    },

    check() {
        this.error = phoneError(this.$refs.input.value, required);
    },
}));

/* ---------------------------------------------------------------------------
   Product card: pick a size, set a quantity, add
--------------------------------------------------------------------------- */
Alpine.data('tradeCard', (lines = [], initial = null) => ({
    lines,
    key: initial,
    qty: 1,
    busy: false,
    error: '',

    get line() { return this.lines.find((l) => l.key === this.key) || null; },

    /** −1 / +1 from the buttons, 0 after typing: either way held to 1…max. */
    step(by) {
        const max = this.line?.max || 1;
        this.qty = Math.min(max, Math.max(1, (parseInt(this.qty, 10) || 1) + by));
    },

    async add() {
        const line = this.line;
        if (!line || this.busy) return;
        this.step(0);
        this.busy = true;
        this.error = '';
        try {
            await Alpine.store('cart').add(line.product_id, null, null, this.qty, true, line.options);
            this.qty = 1;
        } catch (e) {
            this.error = e.payload?.message || 'Could not be added. Please try again.';
        } finally {
            this.busy = false;
        }
    },
}));

/* ---------------------------------------------------------------------------
   Product page: every size of one product, each with its own quantity box
--------------------------------------------------------------------------- */
Alpine.data('orderSheet', (lines = []) => ({
    lines: Object.fromEntries(lines.map((l) => [l.key, l])),
    qty: {},
    errors: {},
    added: {},
    busy: false,

    get filled() {
        return Object.keys(this.qty)
            .filter((key) => this.qty[key] > 0 && this.lines[key])
            .map((key) => ({ ...this.lines[key], qty: this.qty[key] }));
    },
    get units() { return this.filled.reduce((n, l) => n + l.qty, 0); },
    get estimate() { return rupees(this.filled.reduce((n, l) => n + l.qty * l.price, 0)); },

    clamp(key) {
        const line = this.lines[key];
        if (!line) return;
        const n = clampQty(this.qty[key], line.max);
        this.qty[key] = n || '';
        delete this.errors[key];
        delete this.added[key];
    },
    /** The − / + buttons: one at a time, held to 0…max like a typed quantity. */
    step(key, by) {
        this.qty[key] = (parseInt(this.qty[key], 10) || 0) + by;
        this.clamp(key);
    },
    clear() { this.qty = {}; this.errors = {}; },

    async addAll() {
        // A box typed into but never left has not been held to its limit yet.
        Object.keys(this.qty).forEach((key) => this.clamp(key));
        const lines = this.filled;
        if (!lines.length || this.busy) return;
        this.busy = true;
        this.errors = {};
        try {
            const { added, failed } = await addLines(lines);
            for (const key of added) { this.added[key] = this.qty[key]; delete this.qty[key]; }
            this.errors = failed;
            if (added.length) Alpine.store('ui').openCart();
        } finally {
            this.busy = false;
        }
    },
}));

/* ---------------------------------------------------------------------------
   Order form: invoice-style rows — pick an item, set a quantity, add another
--------------------------------------------------------------------------- */
const DRAFT_KEY = 'bulk.order-form';

function readDraft() {
    try { return JSON.parse(localStorage.getItem(DRAFT_KEY) || '[]'); } catch { return []; }
}
function writeDraft(rows) {
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(rows)); } catch { /* private mode: no draft */ }
}

/** Lower-cased search words; every one must appear for an item to match. */
const terms = (q) => String(q || '').toLowerCase().split(/\s+/).filter(Boolean);
const PICK_LIMIT = 60; // results shown at once; typing narrows the rest

Alpine.data('itemsForm', (index = { lines: {}, codes: {} }) => ({
    all: Object.values(index.lines).map((l) => ({
        ...l,
        own: [l.name, l.label, l.unit, l.sku].filter(Boolean).join(' ').toLowerCase(),
        haystack: [l.name, l.label, l.unit, l.sku, l.category].filter(Boolean).join(' ').toLowerCase(),
    })),
    category: '',
    cat: { q: '', open: false, hi: 0 }, // the searchable Category filter
    rows: [],
    nextId: 1,
    busy: false,
    message: '',

    line(key) { return this.all.find((l) => l.key === key) || null; },

    init() {
        // A half-entered order survives a reload or a wander round the catalogue.
        for (const saved of readDraft()) {
            const line = this.line(saved.key);
            if (line) this.rows.push(this.blank({ key: line.key, line, qty: Math.min(saved.qty, line.max) || 1 }));
        }
        if (!this.rows.length) this.rows.push(this.blank());
        this.expandOnly(this.rows[this.rows.length - 1]);
        this.$watch('rows', () => writeDraft(this.rows.filter((r) => r.line).map((r) => ({ key: r.line.key, qty: r.qty }))));
    },

    blank(values = {}) {
        // expanded: on phones each row is a card that folds to its header; desktop ignores it.
        const row = { id: this.nextId++, key: '', line: null, qty: 1, error: '', q: '', open: false, hi: 0, expanded: true, ...values };
        row.q = this.display(row.line);
        return row;
    },
    /** What a row's search box shows once an item is chosen. */
    display(line) {
        return line ? this.title(line) + (line.sku ? ' · ' + line.sku : '') : '';
    },
    title(line) {
        const extra = line.label || line.unit;
        return extra ? `${line.name} (${extra})` : line.name;
    },

    /** Category names in catalogue order, for the filter. */
    get categories() {
        return [...new Set(this.all.map((l) => l.category).filter(Boolean))];
    },
    /** The items the filter allows. */
    get available() {
        return this.category ? this.all.filter((l) => l.category === this.category) : this.all;
    },
    /** A row's search results: the filtered items matching what was typed, grouped by category. */
    results(row) {
        const words = row.open ? terms(row.q) : [];
        const found = this.available.filter((l) => words.every((w) => l.haystack.includes(w)));
        // Items whose own name or code matches come before ones matched only by their category.
        if (words.length) found.sort((a, b) => words.every((w) => b.own.includes(w)) - words.every((w) => a.own.includes(w)));
        const shown = found.slice(0, PICK_LIMIT);
        const groups = [];
        shown.forEach((line, i) => {
            const name = line.category || 'Other';
            let group = groups.find((g) => g.name === name);
            if (!group) groups.push(group = { name, items: [] });
            group.items.push({ line, i });
        });
        return { groups, flat: shown, more: found.length - shown.length };
    },
    openPicker(row) {
        if (row.open) return;
        row.open = true;
        row.q = '';
        row.hi = Math.max(0, this.results(row).flat.findIndex((l) => l.max > 0));
    },
    closePicker(row) {
        row.open = false;
        row.q = this.display(row.line);
    },
    /** Arrow keys move through the results, skipping items that are out of stock. */
    move(row, step) {
        if (!row.open) return this.openPicker(row);
        const flat = this.results(row).flat;
        let i = row.hi;
        for (let n = 0; n < flat.length; n++) {
            i = (i + step + flat.length) % flat.length;
            if (flat[i].max > 0) break;
        }
        row.hi = i;
        this.$nextTick(() => document.getElementById(`bk-opt-${row.id}-${i}`)?.scrollIntoView({ block: 'nearest' }));
    },
    pickHighlighted(row) {
        const line = this.results(row).flat[row.hi];
        if (row.open && line) this.pick(row, line);
    },
    pick(row, line) {
        if (line.max <= 0) return;
        row.key = line.key;
        row.open = false;
        this.choose(row);
        row.q = this.display(row.line);
    },

    /** The Category filter's choices: "All categories" first, then the names matching what was typed. */
    get categoryChoices() {
        const words = this.cat.open ? terms(this.cat.q) : [];
        const names = this.categories.filter((n) => words.every((w) => n.toLowerCase().includes(w)));
        return words.length ? names : ['', ...names];
    },
    openCategory() {
        if (this.cat.open) return;
        this.cat = { q: '', open: true, hi: 0 };
    },
    closeCategory() { this.cat.open = false; this.cat.q = ''; },
    moveCategory(step) {
        if (!this.cat.open) return this.openCategory();
        const n = this.categoryChoices.length;
        if (n) this.cat.hi = (this.cat.hi + step + n) % n;
    },
    pickCategory(name) {
        if (name === undefined) return;
        this.category = name;
        this.closeCategory();
    },

    choose(row) {
        const line = this.line(row.key);
        row.error = '';
        this.message = '';
        if (!line) { row.line = null; return; }
        // The same item twice is one line on the order, so add to the row already there.
        const existing = this.rows.find((r) => r !== row && r.line?.key === line.key);
        if (existing) {
            existing.qty = Math.min(line.max, (existing.qty || 0) + 1);
            row.key = '';
            row.line = null;
            this.message = `${this.title(line)} is already on the order — its quantity went up by one.`;
            return;
        }
        row.line = line;
        row.qty = Math.min(row.qty || 1, line.max);
        this.$nextTick(() => document.getElementById(`bk-qty-${row.id}`)?.select());
    },

    fitQty(row) {
        if (!row.line) return;
        let n = parseInt(row.qty, 10);
        if (isNaN(n) || n < 1) n = 1;
        if (n > row.line.max) {
            row.error = `${row.line.max} is the most for this item`;
            row.expanded = true;
            n = row.line.max;
        } else {
            row.error = '';
        }
        row.qty = n;
    },

    /** Phones: opens or folds a row's card. */
    toggle(row) { row.expanded = !row.expanded; },
    /** Phones: folds every chosen row except this one, so the list stays short. */
    expandOnly(row) {
        for (const r of this.rows) r.expanded = r === row || !r.line || !!r.error;
    },
    /** The card header on phones: the chosen item, or the row's number while it is empty. */
    heading(row) {
        return row.line ? this.title(row.line) : `Item ${this.rows.indexOf(row) + 1}`;
    },

    addRow() {
        const row = this.blank();
        this.rows.push(row);
        this.expandOnly(row);
        this.$nextTick(() => document.getElementById(`bk-item-${row.id}`)?.focus());
    },
    /** Enter in a quantity box moves to the next empty row, or starts one. */
    nextRow(row) {
        this.fitQty(row);
        const after = this.rows[this.rows.indexOf(row) + 1];
        if (after && !after.line) document.getElementById(`bk-item-${after.id}`)?.focus();
        else this.addRow();
    },
    removeRow(row) {
        this.rows = this.rows.filter((r) => r !== row);
        if (!this.rows.length) this.rows.push(this.blank());
    },
    reset() {
        this.rows = [this.blank()];
        this.message = '';
    },

    /** Replaces the rows with a past order's lines (from "Order again"). */
    load(text) {
        const rows = [];
        let skipped = 0;
        for (const { code, qty, invalid } of parsePad(text)) {
            const keys = invalid ? [] : (index.codes[code.toLowerCase()] || []);
            const line = keys.length === 1 ? this.line(keys[0]) : null;
            if (line && line.max > 0) rows.push(this.blank({ key: line.key, line, qty: Math.min(Math.max(1, qty), line.max) }));
            else skipped++;
        }
        this.category = '';
        this.rows = rows.length ? rows : [this.blank()];
        this.expandOnly(this.rows[this.rows.length - 1]);
        this.message = skipped
            ? `${skipped} ${skipped === 1 ? 'item is' : 'items are'} no longer available and ${skipped === 1 ? 'was' : 'were'} left out.`
            : '';
        this.$el.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    },

    get filled() { return this.rows.filter((r) => r.line && r.qty > 0); },
    amount(row) { return row.line ? row.qty * row.line.price : 0; },
    money: rupees,
    rate(n) { return (n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

    async addAll() {
        this.rows.forEach((r) => this.fitQty(r));
        const rows = this.filled;
        if (!rows.length || this.busy) return;
        this.busy = true;
        this.message = '';
        try {
            const { added, failed } = await addLines(rows.map((r) => ({ ...r.line, qty: r.qty })));
            this.rows = this.rows.filter((r) => !(r.line && added.includes(r.line.key)));
            for (const row of this.rows) {
                if (row.line && failed[row.line.key]) row.error = failed[row.line.key];
            }
            if (!this.rows.length) this.rows.push(this.blank());
            if (added.length) {
                this.message = `${added.length} ${added.length === 1 ? 'item' : 'items'} added to your order.`;
                Alpine.store('ui').openCart();
            }
        } finally {
            this.busy = false;
        }
    },
}));

/* ---------------------------------------------------------------------------
   Quick-order pad: pasted codes → checked lines → added together
--------------------------------------------------------------------------- */

/**
 * Reads "CODE, QTY" lines. Commas, semicolons, tabs (a spreadsheet paste) or spaces separate
 * the code from the quantity; a code on its own means one. A header row ("code,qty") is
 * skipped, and the same code twice is one line with the quantities added together.
 */
export function parsePad(text) {
    const rows = [];
    const seen = {};
    for (const raw of String(text || '').split(/\r?\n/)) {
        const line = raw.trim().replace(/^"|"$/g, '');
        if (!line || line.startsWith('#')) continue;
        const match = line.match(/^"?([^",;\t ]+)"?(?:\s*[,;\t ]\s*"?(-?\d+(?:\.\d+)?)"?)?(?:[,;\t].*)?$/);
        if (!match) {
            rows.push({ code: line, qty: 0, invalid: true });
            continue;
        }
        const code = match[1];
        if (match[2] === undefined && /^(sku|code|item|product)([ _-]?(code|no\.?|number))?$/i.test(code) && !rows.length) continue; // header row
        const qty = match[2] === undefined ? 1 : Math.floor(Number(match[2]));
        const id = code.toLowerCase();
        if (seen[id] !== undefined) {
            rows[seen[id]].qty += qty;
        } else {
            seen[id] = rows.length;
            rows.push({ code, qty });
        }
    }
    return rows;
}

Alpine.data('quickOrder', (index = { lines: {}, codes: {} }) => ({
    text: '',
    rows: [],
    checked: false,
    busy: false,
    message: '',

    get ready() { return this.rows.filter((r) => r.line && r.qty > 0 && !r.error); },
    get problems() { return this.rows.filter((r) => r.error).length; },
    get estimate() { return rupees(this.ready.reduce((n, r) => n + r.qty * r.line.price, 0)); },

    /** Matches each pasted code to a line and says, row by row, what will happen to it. */
    check() {
        this.message = '';
        this.rows = parsePad(this.text).map((row) => {
            const keys = row.invalid ? [] : (index.codes[row.code.toLowerCase()] || []);
            const out = { code: row.code, qty: row.qty, line: null, error: '', note: '' };
            if (row.invalid) out.error = 'Not a code and quantity';
            else if (!keys.length) out.error = 'Unknown code';
            else if (keys.length > 1) {
                out.error = 'Code matches several options — order it from the sheet';
                out.line = index.lines[keys[0]];
            } else {
                out.line = index.lines[keys[0]];
                this.fit(out);
            }
            return out;
        });
        this.checked = true;
    },

    /** Holds a matched row's quantity to what can be ordered, noting any change. */
    fit(row) {
        const line = row.line;
        row.error = '';
        row.note = '';
        if (line.stock <= 0) { row.error = 'Out of stock'; return; }
        const wanted = parseInt(row.qty, 10);
        if (isNaN(wanted) || wanted < 1) { row.error = 'Quantity must be at least 1'; return; }
        if (wanted > line.max) {
            row.qty = line.max;
            row.note = `Reduced from ${wanted} — ${line.max} is the most per line`;
        } else {
            row.qty = wanted;
        }
    },

    remove(i) { this.rows.splice(i, 1); },
    reset() { this.text = ''; this.rows = []; this.checked = false; this.message = ''; },

    /** Fills the pad with an order's lines and checks them straight away. */
    load(text) {
        this.text = text;
        this.check();
        this.$el.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    },

    /** A CSV or text file is read in the browser only; nothing is uploaded. */
    readFile(event) {
        const file = event.target.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => { this.text = String(reader.result || ''); this.check(); };
        reader.readAsText(file);
        event.target.value = '';
    },

    /**
     * A ready-to-edit CSV made from this store's own catalogue: a header row, then a few
     * in-stock items with a quantity and the name for reference (the name column is ignored
     * on upload). Built in the browser, so it always matches the current codes.
     */
    downloadSample() {
        const quote = (v) => (/[",\r\n]/.test(v) ? `"${String(v).replace(/"/g, '""')}"` : String(v));
        const picks = Object.values(index.lines).filter((l) => l.sku && l.max > 0).slice(0, 5);
        const qtys = [2, 5, 10, 1, 4];
        const rows = [['code', 'qty', 'name']].concat(picks.map((l, i) => [
            l.sku, Math.min(qtys[i % qtys.length], l.max), l.label ? `${l.name} (${l.label})` : l.name,
        ]));
        // The byte-order mark lets Excel open the ₹ and other characters correctly.
        const csv = '﻿' + rows.map((r) => r.map(quote).join(',')).join('\r\n') + '\r\n';
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        const a = Object.assign(document.createElement('a'), { href: url, download: 'bulk-order-sample.csv' });
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    },

    async addAll() {
        const rows = this.ready;
        if (!rows.length || this.busy) return;
        this.busy = true;
        try {
            const lines = rows.map((r) => ({ ...r.line, qty: r.qty }));
            const { added, failed } = await addLines(lines);
            // Keep only what did not go in, with the reason, so it can be fixed and retried.
            this.rows = this.rows.filter((r) => !(r.line && added.includes(r.line.key) && !r.error));
            for (const row of this.rows) {
                if (row.line && failed[row.line.key]) row.error = failed[row.line.key];
            }
            this.text = this.rows.map((r) => `${r.code}, ${r.qty}`).join('\n');
            this.message = added.length
                ? `${added.length} ${added.length === 1 ? 'line' : 'lines'} added to your order.`
                : '';
            if (!this.rows.length) this.checked = false;
            if (added.length) Alpine.store('ui').openCart();
        } finally {
            this.busy = false;
        }
    },
}));
