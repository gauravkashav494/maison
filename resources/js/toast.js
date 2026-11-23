/**
 * Storefront toast wording, shared by every template so the messages read the same way:
 * a title that names the item ("Basmati Rice (5 kg) added to your cart") and a second line with
 * what is useful next ("Your cart has 2 items, 3 units · ₹1,240.00").
 *
 * Each template's `ui` store keeps its own look; they all call makeToast() to turn what
 * notify() was given (a plain string, or { title, body }) into { title, body, tone }.
 */

const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
const named = (name) => (name ? `“${name}”` : 'This item');

/** "Your cart has 1 item, 3 units · ₹1,620.00": items are lines, units the quantities, as the cart bar says. */
const cartLine = (cart) => {
    const items = cart.items?.length ?? 0;
    const units = cart.count ?? 0;
    if (!items) return null;

    return `Your cart has ${plural(items, 'item')}${units !== items ? `, ${plural(units, 'unit')}` : ''} · ${cart.subtotal_formatted}`;
};

/**
 * notify(message, tone, duration): message is a string or { title, body }. Older calls pass a
 * duration as the second argument (notify('…', 8000)); that still works.
 */
export function makeToast(message, tone = 'ok', duration = null) {
    if (typeof tone === 'number') {
        duration = tone;
        tone = 'ok';
    }
    const toast = typeof message === 'object' && message !== null
        ? { title: message.title ?? '', body: message.body ?? null }
        : { title: String(message ?? ''), body: null };
    toast.tone = tone || 'ok';
    // A second line needs a moment longer to read.
    toast.duration = duration ?? (toast.body ? 4000 : 2600);

    return toast;
}

export const toasts = {
    /** After the cart accepted a product: its name and size, and the cart as it is now. */
    addedToCart(cart, productId, size = null, qty = 1) {
        const line = cart.items?.find((i) => i.product.id === productId && (size === null || i.size === size));
        const name = line?.product?.name;
        const what = name ? `${name}${line.size ? ` (${line.size})` : ''}` : 'Item';
        return {
            title: `${qty > 1 ? `${qty} × ` : ''}${what} added to your cart`,
            body: cartLine(cart),
        };
    },

    /** The cart refused: the server's reason ("Only 3 left in stock.") as the second line. */
    cartFailed(error, adding = true) {
        return {
            title: adding ? 'Couldn’t add this item' : 'Couldn’t update your cart',
            body: error?.payload?.message || 'Please try again in a moment.',
        };
    },

    /** Something in the cart changed on the server's side (hidden or no longer sold). */
    cartChanged(message) {
        return { title: 'Your cart has changed', body: message };
    },

    maxQty(max, name = null) {
        return {
            title: 'Order limit reached',
            body: `You can order up to ${max} of ${name ? `“${name}”` : 'this item'} at a time.`,
        };
    },

    couponApplied(cart, code) {
        const applied = (cart.coupon?.code || code || '').toString().toUpperCase();

        return {
            title: applied ? `Coupon ${applied} applied` : 'Coupon applied',
            body: Number(cart.discount) > 0 ? `You save ${cart.discount_formatted} on this order.` : null,
        };
    },

    /** Wishlist heart. `list` is what the template calls it: "wishlist", "list". */
    wishlist(saved, name, count, list = 'wishlist') {
        return saved
            ? { title: `${named(name)} saved to your ${list}`, body: `You have ${plural(count, 'item')} in your ${list}.` }
            : { title: `${named(name)} removed from your ${list}`, body: count ? `${plural(count, 'item')} left in your ${list}.` : `Your ${list} is empty.` };
    },

    /** "Notify me" on an out-of-stock product: it is kept in the list to check back on. */
    keptForLater(name, list = 'list') {
        return { title: `${named(name)} is in your ${list}`, body: 'Check back there once it is in stock again.' };
    },

    savedForLater(name) {
        return { title: `${named(name)} saved for later`, body: 'It’s below your cart, ready to move back whenever you like.' };
    },

    linkCopied() {
        return { title: 'Link copied', body: 'Paste it anywhere to share this page.' };
    },

    preview() {
        return { title: 'Preview only', body: 'This is a sample item, so no order is placed.' };
    },
};
