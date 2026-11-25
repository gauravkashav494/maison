/*
 * Saved items (the wishlist), shared by every template's `wishlist` store.
 *
 * An entry is what the shopper saved:
 *   - a product id (99): the product, no size chosen. Saved from a card, or from a product page
 *     before every option was picked. Older lists hold only these.
 *   - { id: 99, options: { Size: 'M' } }: exactly that size / colour, as it would go in the cart.
 *
 * Hearts on cards ask about the product: filled when anything of it is saved, and tapping a
 * filled one takes all of it off. Hearts on a product page, a size row or a cart line ask about
 * that exact choice. Saving a size replaces a "no size" entry of the same product, as it is the
 * more exact wish.
 */
import { chosenValue } from './variants';

const norm = (s) => String(s).trim().toLowerCase().replace(/^colors?$|^colours?$/, 'colour').replace(/^sizes$/, 'size');

/** Options with empty values dropped, or null when nothing is left. */
function clean(options) {
    const out = {};
    for (const [name, value] of Object.entries(options || {})) {
        if (value !== null && value !== undefined && String(value).trim() !== '') out[name] = String(value);
    }
    return Object.keys(out).length ? out : null;
}

/** Same options, however the names are spelt (Colour / Color, Size / Sizes) and in any order. */
export function sameOptions(a, b) {
    const key = (o) => JSON.stringify(Object.entries(o || {}).map(([n, v]) => [norm(n), String(v).toLowerCase()]).sort(([x], [y]) => (x < y ? -1 : 1)));
    return key(a) === key(b);
}

export const entryId = (e) => Number(typeof e === 'object' && e !== null ? e.id : e);
export const entryOptions = (e) => (typeof e === 'object' && e !== null ? clean(e.options) : null);

/** The entry for a product and (optionally) a choice. */
export function makeEntry(id, options = null) {
    const chosen = clean(options);
    return chosen ? { id: Number(id), options: chosen } : Number(id);
}

/** One entry per thing saved; entries that are not ids or { id, options } are dropped. */
export function tidy(list) {
    const out = [];
    for (const raw of Array.isArray(list) ? list : []) {
        const id = entryId(raw);
        if (!Number.isInteger(id) || id <= 0) continue;
        const entry = makeEntry(id, entryOptions(raw));
        if (!out.some((e) => isSame(e, entry))) out.push(entry);
    }
    return out;
}

function isSame(a, b) {
    if (entryId(a) !== entryId(b)) return false;
    const x = entryOptions(a);
    const y = entryOptions(b);
    return x === null || y === null ? x === y : sameOptions(x, y);
}

/**
 * The full choice made on a product page, or null while an option is still unpicked. `product`
 * is the card (Product::toCard()), whose `choices` are the options that have more than one value.
 */
export function chosenOptions(product, ctx) {
    const choices = product?.choices || [];
    if (!choices.length) return null;
    const options = {};
    for (const { name } of choices) {
        const value = chosenValue(ctx, name);
        if (value === null || value === undefined || value === '') return null;
        options[name] = value;
    }
    return options;
}

/** "Size: M" / "Colour: Navy · Size: M", for messages and labels. */
export function describe(options) {
    return Object.entries(options || {}).map(([n, v]) => `${n}: ${v}`).join(' · ');
}

/**
 * Gives a `wishlist` store its list methods. The store keeps its entries in `ids` (the name
 * older lists were stored under). `notify(saved, name, count)` says what a heart did;
 * `changed` runs after every change. Copied as property descriptors so `count` stays live.
 */
export function withSavedItems(store, { notify = null, changed = () => {} } = {}) {
    return Object.defineProperties(store, Object.getOwnPropertyDescriptors(savedItemsMethods(notify, changed)));
}

function savedItemsMethods(notify, changed) {
    return {
        /**
         * No options (or null: nothing fully chosen yet): is anything of this product saved?
         * With options: is this exact choice?
         */
        has(id, options = null) {
            id = Number(id);
            if (!clean(options)) return this.ids.some((e) => entryId(e) === id);
            const wanted = makeEntry(id, options);
            return this.ids.some((e) => isSame(e, wanted));
        },
        /**
         * Heart tapped. Without options (a card): saves the product, or takes off everything of
         * it. With options (a product page, a size row): saves or takes off that choice.
         */
        toggle(id, name = null, options = null) {
            id = Number(id);
            const exact = clean(options) !== null;
            const entry = makeEntry(id, options);
            let saved;
            if (this.has(id, options)) {
                this.ids = this.ids.filter((e) => (exact ? !isSame(e, entry) : entryId(e) !== id));
                saved = false;
            } else {
                // A size replaces "this product, no size".
                const rest = typeof entry === 'object' ? this.ids.filter((e) => !(entryId(e) === id && !entryOptions(e))) : this.ids;
                this.ids = [entry, ...rest];
                saved = true;
            }
            const label = entryOptions(entry) ? `${name ?? 'Item'} (${Object.values(entryOptions(entry)).join(' / ')})` : name;
            notify?.(saved, label, this.ids.length);
            changed.call(this);
        },
        /** Puts an entry in the list if it is not there (never takes it out). */
        add(id, options = null) {
            const entry = makeEntry(id, options);
            if (!this.ids.some((e) => isSame(e, entry))) {
                const rest = typeof entry === 'object' ? this.ids.filter((e) => !(entryId(e) === Number(id) && !entryOptions(e))) : this.ids;
                this.ids = [entry, ...rest];
                changed.call(this);
            }
        },
        get count() { return this.ids.length; },
        /** For the query string of the Saved items list (?items=…). */
        get query() { return `items=${encodeURIComponent(JSON.stringify(this.ids))}`; },
    };
}
