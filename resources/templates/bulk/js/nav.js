/**
 * Bulk Order template — the category bar.
 *
 * On desktop the bar shows as many categories as fit beside "Bulk order", "All products" and
 * the header links, and folds the rest into "More". Category names vary in length from store
 * to store, so a fixed count would either waste room or overflow. Phones scroll the row instead.
 */
import Alpine from 'alpinejs';

const desktop = window.matchMedia('(min-width: 64rem)');
const width = (el) => (el ? el.getBoundingClientRect().width : 0);

Alpine.data('catBar', () => ({
    fit: Infinity,  // how many categories are shown inline
    widths: [],     // each category link's width, measured while all are shown
    moreWidth: 96,  // the "More" button, until it has been measured

    init() {
        const refit = () => this.refit();
        new ResizeObserver(refit).observe(this.$refs.row);
        desktop.addEventListener('change', refit);
        // Widths change once the web font arrives: measure again from scratch.
        document.fonts?.ready.then(() => { this.widths = []; this.fit = Infinity; this.$nextTick(refit); });
    },

    refit() {
        if (!desktop.matches) { this.fit = Infinity; return; }
        const { row, main, side } = this.$refs;
        const more = row.querySelector('.bk-catmore');
        const cats = [...main.querySelectorAll('[data-cat]')];
        if (!this.widths.length) {
            if (this.fit !== Infinity) { this.fit = Infinity; return this.$nextTick(() => this.refit()); }
            this.widths = cats.map(width);
        }
        if (more && more.offsetParent) this.moreWidth = width(more);

        const style = getComputedStyle(row);
        const fixed = [...main.querySelectorAll('[data-fixed]')].reduce((n, el) => n + width(el), 0);
        const room = row.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight) - width(side) - fixed;

        const all = this.widths.reduce((n, w) => n + w, 0);
        if (all <= room) { this.fit = Infinity; return; }
        let used = 0;
        let n = 0;
        while (n < this.widths.length && used + this.widths[n] <= room - this.moreWidth) used += this.widths[n++];
        this.fit = n;
    },
}));
