{{--
    "Where it shows" on the menu form: the store's header or footer holding this menu, at desktop
    width and scaled to fit, opened with ?menu_preview=<area> so the store shows only that section
    and outlines the menu in orange. It shows the saved links and reloads by itself when the menu items change.
--}}
<div class="space-y-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">Where it shows</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $placement ? $placement.'.' : '' }} The links of this menu are outlined in orange.</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- The whole store page in a new tab, scrolled to this menu and outlined. --}}
            <x-filament::button size="sm" color="primary" icon="heroicon-o-arrow-top-right-on-square" tag="a" :href="$pageUrl ?? $url" target="_blank">
                View on storefront
            </x-filament::button>
        </div>
    </div>

    {{-- Drawn as wide as the panel, but never narrower than 1280px (a computer screen, so the header
         shows its full menu; a narrower panel scales it down), and as tall as the section the store
         reports back (only the header or footer is shown). --}}
    <div data-store-preview
        x-data="{
            frameWidth: 1280,
            scale: 1,
            height: 220,
            fit() {
                this.frameWidth = Math.max(1280, this.$el.clientWidth);
                this.scale = this.$el.clientWidth / this.frameWidth;
            },
        }"
        x-init="fit(); new ResizeObserver(() => fit()).observe($el)"
        x-on:menu-items-changed.window="setTimeout(() => { $refs.frame.src = $refs.frame.src }, 150)"
        x-on:message.window="if ($event.source === $refs.frame.contentWindow && $event.data && $event.data.menuPreviewHeight) height = $event.data.menuPreviewHeight"
        x-bind:style="`height: ${Math.ceil(height * scale)}px`"
        class="relative w-full overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10"
        style="height: 220px">
        <iframe x-ref="frame" src="{{ $url }}" title="Store preview" loading="lazy" scrolling="no"
            class="absolute left-0 top-0 origin-top-left border-0 bg-white"
            x-bind:style="`width: ${frameWidth}px; height: ${height}px; transform: scale(${scale})`"
            style="width: 1280px; height: 220px"></iframe>
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400">Shows the saved menu, and refreshes when you change its items.</p>
</div>
