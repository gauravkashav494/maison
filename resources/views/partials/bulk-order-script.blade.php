{{--
    Behaviour shared by every template's Bulk order page; each template only supplies
    its own markup. Search goes through /api/search, which already returns just the
    products of the store being served, so one store's page can never list or add
    another store's products.
--}}
<script>
function bulkOrder(suggestions = []) {
    return {
        rows: [],
        nextKey: 1,
        submitting: false,
        error: null,
        suggestions,
        init() {
            this.addRow();
        },
        addRow() {
            this.rows.push({ key: this.nextKey++, query: '', results: [], open: false, searching: false, product: null, size: '', color: null, qty: 1, timer: null, anchorEl: null, pos: { top: 0, left: 0, width: 0 } });
        },
        // The suggestions panel is teleported to <body> so it isn't clipped by the
        // table's rounded-corner "overflow-hidden" card — this computes where to put it.
        anchorPos(inputEl) {
            const r = inputEl.getBoundingClientRect();
            return { top: r.bottom + window.scrollY + 4, left: r.left + window.scrollX, width: r.width };
        },
        removeRow(key) {
            this.rows = this.rows.filter((r) => r.key !== key);
            if (!this.rows.length) this.addRow();
        },
        // Before anything is typed, the panel offers a few popular items instead of sitting empty.
        listFor(row) {
            return row.query.trim() ? row.results : this.suggestions;
        },
        // "SKU · size" under a suggestion; products with several sizes say so instead of naming one.
        meta(p) {
            const size = p.sizes.length > 1 ? p.sizes.length + ' sizes' : (p.sizes[0] ?? '');
            return [p.sku, size].filter(Boolean).join(' · ');
        },
        search(row) {
            clearTimeout(row.timer);
            const q = row.query.trim();
            if (!q) { row.results = []; return; }
            row.timer = setTimeout(async () => {
                row.searching = true;
                try {
                    const res = await fetch('/api/search?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    row.results = data.products ?? [];
                } catch { row.results = []; }
                finally { row.searching = false; }
            }, 250);
        },
        pick(row, product) {
            row.product = product;
            row.query = product.name;
            row.results = [];
            row.open = false;
            row.size = product.sizes[0] ?? '';
            row.color = product.colors[0]?.name ?? null;
            row.qty = 1;
        },
        clear(row) {
            row.product = null;
            row.query = '';
            row.open = false;
        },
        maxQty(row) {
            return row.product?.max_qty || 99;
        },
        setQty(row, n) {
            row.qty = Math.min(this.maxQty(row), Math.max(1, Number(n) || 1));
        },
        validRows() {
            return this.rows.filter((r) => r.product && r.qty > 0);
        },
        subtotal() {
            return this.validRows().reduce((sum, r) => sum + r.product.price * r.qty, 0);
        },
        fmt(n) {
            return '₹' + Math.round(n).toLocaleString('en-IN');
        },
        async placeOrder() {
            const rows = this.validRows();
            if (!rows.length) return;
            this.submitting = true;
            this.error = null;
            try {
                for (const row of rows) {
                    await Alpine.store('cart').add(row.product.id, row.size, row.color, row.qty, false);
                }
                window.location.href = '/cart';
            } catch {
                this.error = 'Something went wrong adding these items. Please try again.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
