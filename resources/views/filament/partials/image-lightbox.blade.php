{{--
    Admin image uploads (Fields::image), both panels:

    1. A click on a saved image (or its open icon) shows it large in a lightbox instead of a new
       tab. Esc, a click on the backdrop or × closes it. A drag to reorder is not a click.
    2. Filament's upload box downloads every saved image with the browser cache switched off
       (fetch(..., { cache: 'no-store' })), so each image was fetched again in full whenever a
       form opened. For our own /storage files the normal cache is used instead.
--}}
<script>
    (() => {
        if (window.__adminImageLightbox) return;
        window.__adminImageLightbox = true;

        const nativeFetch = window.fetch.bind(window);
        window.fetch = (input, init) => {
            const url = typeof input === 'string' ? input : input?.url;
            if (init?.cache === 'no-store' && url) {
                try {
                    if (new URL(url, location.href).pathname.startsWith('/storage/')) {
                        const { cache, ...rest } = init;
                        return nativeFetch(input, rest);
                    }
                } catch (e) {}
            }
            return nativeFetch(input, init);
        };

        let box = null;
        const close = () => { box?.remove(); box = null; document.removeEventListener('keydown', onKey); };
        const onKey = (e) => { if (e.key === 'Escape') close(); };
        const open = (src) => {
            close();
            box = document.createElement('div');
            box.className = 'fi-lightbox';
            box.setAttribute('role', 'dialog');
            box.setAttribute('aria-modal', 'true');
            box.innerHTML = '<button type="button" class="fi-lightbox-close" aria-label="Close">&times;</button>'
                + '<a class="fi-lightbox-new" target="_blank" rel="noopener">Open in new tab</a><img alt="">';
            box.querySelector('img').src = src;
            box.querySelector('.fi-lightbox-new').href = src;
            box.addEventListener('click', (e) => { if (! e.target.closest('img, .fi-lightbox-new')) close(); });
            document.body.appendChild(box);
            document.addEventListener('keydown', onKey);
            box.querySelector('.fi-lightbox-close').focus();
        };

        // Tell a click from the start of a drag (tiles can be dragged to reorder).
        let down = null;
        document.addEventListener('pointerdown', (e) => { down = { x: e.clientX, y: e.clientY }; }, true);

        document.addEventListener('click', (e) => {
            const item = e.target.closest('.fi-fo-file-upload .filepond--item');
            if (! item || e.button !== 0) return;
            // Remove, edit, download and the like keep doing their own job.
            if (e.target.closest('button') || e.target.closest('a:not(.filepond--open-icon)')) return;
            if (down && Math.hypot(e.clientX - down.x, e.clientY - down.y) > 5) return;
            const link = item.querySelector('a.filepond--open-icon');
            if (! link?.href) return;
            e.preventDefault();
            e.stopPropagation();
            open(link.href);
        }, true);
    })();
</script>
