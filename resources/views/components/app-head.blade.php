{{-- Installable web app tags shared by every template's layout (manifest, iOS home-screen icon, full-screen mode). --}}
@php
    $tpl = template();
    $pwa = $tpl->pwa();
    $iconBase = '/templates/'.$tpl->id();
    $appName = $pwa['name'] ?? setting('site.name', config('app.name'));
@endphp
<link rel="manifest" href="{{ route('pwa.manifest') }}">
{{-- A signed-in buyer's Saved items, which the wishlist store (resources/js/storefront.js) merges with the device's. --}}
@auth('web')
    <meta name="account-wishlist" content="{{ json_encode(array_values(auth('web')->user()->wishlist ?? [])) }}" data-account="{{ auth('web')->id() }}">
@endauth
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $appName }}">
<meta name="application-name" content="{{ $appName }}">
<link rel="apple-touch-icon" href="{{ $iconBase }}/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="64x64" href="{{ $iconBase }}/favicon.png">
<link rel="icon" type="image/png" sizes="192x192" href="{{ $iconBase }}/icon-192.png">
{{--
    Menu preview (Content → Navigation → a menu → "Where it shows"): opened as
    ?menu_preview=<area>, the page keeps only the header or footer holding that menu (marked
    data-menu-area="<area>" in the header and footer partials), outlines the menu in it, and
    tells the admin page how tall it is. It only changes what this one view shows; nothing is saved.
--}}
{{--
    Settings preview (Settings → Site settings → a tab → "View on storefront"): opened as
    ?settings_preview=<tab>, the page outlines in orange each place showing that tab's settings
    (found by their saved values: the email, phone, social links, payment badges…), scrolls to
    the first, and says so in a note. Only known tabs are read; nothing is saved.
--}}
@php
    $settingsPreviewKey = (string) request()->query('settings_preview');
    $settingsPreview = null;
    if (in_array($settingsPreviewKey, ['brand', 'contact', 'checkout'], true)) {
        $site = (array) setting('site', []);
        $filled = fn (array $values) => array_values(array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : null, $values)));
        $payments = $filled((array) ($site['payment_methods'] ?? []));
        $settingsPreview = match ($settingsPreviewKey) {
            'brand' => [
                // The logo in the header (marked data-store-logo in each design's header).
                'items' => ['[data-store-logo]'],
                'found' => 'Your brand is shown here. The logo text is changed in Appearance → Settings; the site name is the browser-tab title.',
                'none' => 'The site name is the browser-tab title. The logo text is changed in Appearance → Settings.',
            ],
            'contact' => [
                'emails' => $filled([$site['contact_email'] ?? null]),
                'phones' => $filled([$site['contact_phone'] ?? null]),
                'links' => $filled([$site['social_instagram'] ?? null, $site['social_pinterest'] ?? null, $site['social_facebook'] ?? null, $site['social_youtube'] ?? null, $site['social_linkedin'] ?? null]),
                'texts' => $payments,
                'found' => 'Your contact details, social links and payment badges are outlined in orange.',
                'none' => 'None of these show on this page: fill them in first, or this design shows its own support details (Appearance → its Settings page → Support).',
            ],
            'checkout' => [
                // On the checkout page (a sample bag, see CartController::checkout): each choice the
                // tab sets. Elsewhere only the free-shipping bar.
                'selectors' => ['[x-show*="free_shipping_threshold"]'],
                'groups' => ['shipping_method', 'payment_method'],
                'summary' => true,
                'cart' => $settingsPreviewCart ?? null,
                'found' => ($settingsPreviewCart ?? null)
                    ? 'Preview with a sample item: shipping methods, payment methods and the tax summary are outlined in orange. Orders cannot be placed from this preview.'
                    : 'The free-shipping bar is outlined in orange. Shipping methods, payment methods and tax show at checkout.',
                'none' => 'Shipping methods, payment methods, tax and the free-shipping bar show at checkout. There is no product in stock to preview it with.',
            ],
        };
    }
@endphp
@if($settingsPreview)
    <meta name="robots" content="noindex">
    <style>
        /* A link or badge: outlined and tinted. A whole box (shipping choices, totals): an orange
           outline drawn just inside it, so a card's own edges or overflow never clip it, and no tint, so
           the chosen option keeps its own colour. */
        [data-settings-preview-hit="item"] { outline: 3px solid #f59e0b !important; outline-offset: 3px; border-radius: 4px; background-color: rgb(245 158 11 / .18) !important; }
        [data-settings-preview-hit="box"] { outline: 3px solid #f59e0b !important; outline-offset: -3px; border-radius: 8px; }
        [data-settings-preview-banner] { position: fixed; left: 50%; bottom: 16px; transform: translateX(-50%); z-index: 9999; max-width: min(92vw, 640px); text-align: center; background: #0f172a; color: #fff; font: 600 13px/1.4 system-ui, sans-serif; padding: 9px 14px; border-radius: 18px; box-shadow: 0 8px 24px rgb(0 0 0 / .25); cursor: pointer; }
        [x-show*="cookies.decided"] { display: none !important; }
        html { scroll-behavior: auto !important; }
    </style>
    <script>
        // The sample bag (checkout preview): the cart store shows it instead of the real, empty one,
        // set before Alpine starts; and no order is sent from the preview.
        (() => {
            const cart = @js($settingsPreview['cart'] ?? null);
            if (! cart) return;
            document.addEventListener('alpine:init', () => {
                const store = window.Alpine.store('cart');
                if (store) store.load = async function () { this.apply(cart); };
            });
            document.addEventListener('submit', (event) => {
                event.preventDefault();
                event.stopImmediatePropagation();
                window.Alpine?.store('ui')?.notify({ title: 'Preview only', body: 'This is a sample item, so no order is placed.' }, 6000);
            }, true);
        })();
        window.addEventListener('load', () => {
            const preview = @js($settingsPreview);
            const shown = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
            const digits = (value) => String(value).replace(/\D/g, '').slice(-10);
            const plain = (value) => String(value).trim().toLowerCase().replace(/\s+/g, ' ');
            const url = (value) => String(value).trim().toLowerCase().replace(/^https?:\/\/(www\.)?/, '').replace(/\/+$/, '');

            const find = () => {
                const hits = new Set();
                const boxes = new Set();
                // Radio choices (shipping, payment): the box holding all of them.
                (preview.groups || []).forEach((name) => {
                    const radios = [...document.querySelectorAll('input[name="' + name + '"]')];
                    if (! radios.length) return;
                    let box = radios[0].closest('label') || radios[0];
                    while (box.parentElement && ! radios.every((r) => box.contains(r))) box = box.parentElement;
                    if (radios.length === 1) box = box.parentElement || box;
                    boxes.add(box);
                });
                if (preview.summary) {
                    const tax = document.querySelector('template[x-for*="taxRows"]');
                    tax?.parentElement && boxes.add(tax.parentElement);
                }
                (preview.selectors || []).forEach((s) => document.querySelectorAll(s).forEach((el) => boxes.add(el)));
                (preview.items || []).forEach((s) => document.querySelectorAll(s).forEach((el) => hits.add(el)));
                // Links: social profiles, mailto: and tel:.
                const links = (preview.links || []).map(url);
                const emails = (preview.emails || []).map(plain);
                const phones = (preview.phones || []).map(digits).filter((d) => d.length >= 6);
                document.querySelectorAll('body a[href]').forEach((a) => {
                    const href = a.getAttribute('href');
                    if (links.includes(url(href))
                        || (href.startsWith('mailto:') && emails.includes(plain(href.slice(7).split('?')[0])))
                        || (href.startsWith('tel:') && phones.includes(digits(href)))) {
                        hits.add(a);
                    }
                });
                // Text: an email or phone written out, a payment badge, the site name.
                const texts = (preview.texts || []).map(plain);
                const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
                for (let node = walker.nextNode(); node; node = walker.nextNode()) {
                    const el = node.parentElement;
                    if (! el || el.closest('script, style, template, [data-settings-preview-banner]')) continue;
                    const text = plain(node.textContent);
                    if (! text) continue;
                    if (texts.includes(text)
                        || emails.some((e) => text.includes(e))
                        || phones.some((d) => digits(text).length >= 10 && text.replace(/\D/g, '').includes(d))) {
                        hits.add(el.closest('a') || el);
                    }
                }

                return [...[...boxes].map((el) => [el, 'box']), ...[...hits].filter((el) => ! boxes.has(el)).map((el) => [el, 'item'])]
                    .filter(([el]) => shown(el));
            };

            const note = (text) => {
                const banner = document.createElement('div');
                banner.setAttribute('data-settings-preview-banner', '');
                banner.title = 'Click to hide';
                banner.textContent = text;
                banner.addEventListener('click', () => banner.remove());
                document.body.appendChild(banner);
            };

            const outline = (hits) => {
                note(hits.length ? preview.found : preview.none);
                hits.forEach(([el, kind]) => el.setAttribute('data-settings-preview-hit', kind));
                if (! hits.length) return;
                const show = () => hits[0][0].scrollIntoView({ block: 'center', behavior: 'instant' });
                show();
                setTimeout(show, 400);
                setTimeout(show, 1200);
            };

            outline(find());
        });
    </script>
@endif
@php $menuPreview = preg_match('/^[a-z_]{1,40}$/', (string) request()->query('menu_preview')) ? request()->query('menu_preview') : null; @endphp
@php $menuPreviewPage = $menuPreview && request()->boolean('menu_preview_page'); @endphp
@if($menuPreview)
    <meta name="robots" content="noindex">
    <style>
        [data-menu-preview-hit] { outline: 3px solid #f59e0b !important; outline-offset: 4px; border-radius: 4px; background-color: rgb(245 158 11 / .14) !important; }
        /* The cookie notice is not part of what is being previewed. The note sits at the top, since
           footer menus (and policy links on the bottom line) end up at the bottom of the screen. */
        [x-show*="cookies.decided"] { display: none !important; }
        html { scroll-behavior: auto !important; }
        @unless($menuPreviewPage)
        html, body { min-height: 0 !important; height: auto !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; }
        [data-menu-preview-section] { position: static !important; top: auto !important; transform: none !important; margin: 0 !important; }
        @endunless
        [data-menu-preview-banner] { position: fixed; left: 50%; top: 16px; transform: translateX(-50%); z-index: 9999; background: #0f172a; color: #fff; font: 600 13px/1.4 system-ui, sans-serif; padding: 9px 14px; border-radius: 999px; box-shadow: 0 8px 24px rgb(0 0 0 / .25); }
        [data-menu-preview-note] { margin: 0; padding: 28px 24px; font: 600 15px/1.5 system-ui, sans-serif; color: #334155; background: #f8fafc; text-align: center; }
    </style>
    <script>
        window.addEventListener('load', () => {
            const area = @js($menuPreview);
            // Only what is on screen at this width (the phone menu is hidden on desktop).
            const hits = [...document.querySelectorAll('[data-menu-area="' + area + '"]')]
                .filter((el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden');

            // "View on storefront": the whole page, scrolled to the menu and outlined.
            if (@js($menuPreviewPage)) {
                const banner = document.createElement('div');
                banner.setAttribute('data-menu-preview-banner', '');
                banner.textContent = hits.length ? 'This menu is outlined in orange.' : 'This menu is not shown on this page: it has no links yet, or this store’s design does not use it.';
                document.body.appendChild(banner);
                hits.forEach((el) => el.setAttribute('data-menu-preview-hit', ''));
                if (hits.length) {
                    const show = () => hits[0].scrollIntoView({ block: 'center', behavior: 'instant' });
                    show();
                    setTimeout(show, 400);
                    setTimeout(show, 1200);
                }
                return;
            }

            // The header or footer holding the menu; with nothing to show, a note on its own.
            let section = hits.length ? (hits[0].closest('header, footer') || hits[0]) : null;
            if (! section) {
                section = document.createElement('p');
                section.setAttribute('data-menu-preview-note', '');
                section.textContent = 'This menu is not shown on the store: it has no links yet, or this store’s design does not use it.';
                document.body.prepend(section);
            }

            // Keep only that section: hide everything beside it on the way up to <body>.
            section.setAttribute('data-menu-preview-section', '');
            for (let el = section; el && el !== document.body; el = el.parentElement) {
                for (const other of el.parentElement.children) {
                    if (other !== el && ! ['SCRIPT', 'STYLE', 'LINK', 'TEMPLATE'].includes(other.tagName)) {
                        other.style.setProperty('display', 'none', 'important');
                    }
                }
            }
            // Links with no shared box (Heritage, Plumbing Services headers) are outlined one by one.
            hits.forEach((el) => el.setAttribute('data-menu-preview-hit', ''));

            // The admin page sizes its preview to the section (it may load later, e.g. web fonts).
            const report = () => window.parent !== window && window.parent.postMessage({ menuPreviewHeight: Math.ceil(section.getBoundingClientRect().height) + 12 }, '*');
            report();
            setTimeout(report, 400);
            setTimeout(report, 1200);
            new ResizeObserver(report).observe(section);
        });
    </script>
@endif
