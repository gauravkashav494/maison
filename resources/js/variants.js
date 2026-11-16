/*
 * Product variants on the storefront, shared by every template bundle.
 *
 * A customer's choice comes from two places: the `options` object filled by the shared
 * <x-variant-options> pickers, and the template's own Size and Colour pickers, which set the
 * page's `size` and `color`. Both are read here, so a template never has to know which one an
 * option uses. Option names are matched the way the server matches them: Colour and Color
 * (and Size / Sizes) are the same option.
 */

const isSize = (name) => /^sizes?$/i.test(name);
const isColour = (name) => /^colou?rs?$/i.test(name);
const same = (a, b) => String(a).toLowerCase() === String(b).toLowerCase();

/** The value the customer has chosen for an option, or null. */
export function chosenValue(ctx, name) {
    const options = ctx.options || {};
    for (const [key, value] of Object.entries(options)) {
        if (value !== null && value !== '' && (key === name || (isColour(key) && isColour(name)) || (isSize(key) && isSize(name)))) return value;
    }
    if (isSize(name) && ctx.size) return ctx.size;
    if (isColour(name) && ctx.color) return ctx.color;
    return null;
}

/** The combination matching every option it is defined by, or null while one is unchosen. */
export function matchVariant(variants, ctx) {
    return variants.find((v) => Object.entries(v.options).every(([name, value]) => {
        const chosen = chosenValue(ctx, name);
        return chosen !== null && same(chosen, value);
    })) || null;
}

/**
 * Gallery images for the current choice: the photos of the combination that best fits what
 * has been chosen so far (picking only Navy already shows the navy photos), followed by the
 * product's other images. The product's own images when nothing chosen has photos.
 */
export function galleryImages(product, ctx) {
    let best = null;
    let bestScore = 0;
    for (const v of product.variant_images || []) {
        let score = 0;
        let looks = 0; // matches on options other than Size, which is what photos usually show
        let fits = true;
        for (const [name, value] of Object.entries(v.options)) {
            const chosen = chosenValue(ctx, name);
            if (chosen === null) continue;
            if (!same(chosen, value)) { fits = false; break; }
            score++;
            if (!isSize(name)) looks++;
        }
        // Picking only a size does not switch to, say, the navy photos; it does when the
        // combination is told apart by size alone.
        const sizeOnly = Object.keys(v.options).every(isSize);
        if (fits && (looks > 0 || (sizeOnly && score > 0)) && score > bestScore) { best = v; bestScore = score; }
    }
    if (!best) return product.images;

    return [...best.images, ...product.images.filter((src) => !best.images.includes(src))];
}

/**
 * Gallery slides (images, then the video) for a product page component, and a watcher that
 * goes back to the first slide when the choice changes the photos. Call `watchVariantGallery`
 * from the component's init().
 */
export function gallerySlides(product, ctx) {
    const slides = galleryImages(product, ctx).map((src) => ({ type: 'image', src }));
    if (product.video_url) slides.push({ type: 'video', src: product.video_url });
    return slides;
}

export function watchVariantGallery(ctx, product) {
    if (!(product.variant_images || []).length) return;
    let shown = galleryImages(product, ctx)[0];
    const reset = () => {
        const first = galleryImages(product, ctx)[0];
        if (first !== shown) { shown = first; ctx.image = 0; }
    };
    // Only what this page has: a template without a colour picker has no `color`.
    for (const key of ['options', 'size', 'color']) {
        if (key in ctx) ctx.$watch(key, reset);
    }
}

/** Registers the live price / stock line under the variant pickers. Call before Alpine.start(). */
export function registerVariants(Alpine) {
    Alpine.data('variantPricing', (variants) => ({
        get current() { return matchVariant(variants, this); },
    }));
}
